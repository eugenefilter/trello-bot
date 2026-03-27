<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use Illuminate\Support\Facades\Log;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\AttachmentResult;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use Throwable;

/**
 * Оркестрирует обработку одного Telegram update.
 * Порядок для обычного сообщения:
 *   1. Загружает payload из репозитория
 *   2. Парсит payload → TelegramMessageDTO (null = неподдерживаемый тип)
 *   3. Ищет routing rule → RoutingResultDTO (null = нет подходящего правила)
 *   4. Рендерит шаблоны карточки через CardTemplateRenderer
 *   5. Создаёт карточку в Trello
 *   6. Отправляет подтверждение пользователю через Telegram
 *   7. Помечает сообщение как обработанное
 * Для "догоняющего" update медиагруппы (карточка уже создана):
 *   1-2. Парсит payload
 *   3. Находит существующую карточку по media_group_id
 *   4. Скачивает и прикрепляет файлы к существующей карточке
 *   5. Помечает сообщение как обработанное (без reply и без новой карточки)
 * При исключении из TrelloCardCreator markProcessed не вызывается —
 * Job повторит обработку согласно политике retry.
 */
class TelegramUpdateProcessor
{
    public function __construct(
        private readonly TelegramMessageRepositoryInterface $repository,
        private readonly UpdateParserInterface $parser,
        private readonly RoutingEngineInterface $routing,
        private readonly TrelloCardCreator $cardCreator,
        private readonly CardTemplateRenderer $renderer,
        private readonly TelegramAdapterInterface $telegram,
        private readonly TrelloAdapterInterface $trello,
        private readonly TelegramFileDownloader $fileDownloader,
        private readonly CardLogRepositoryInterface $cardLog,
    ) {}

    /**
     * @throws Throwable при ошибке Trello — Job уйдёт в retry
     */
    public function process(int $telegramMessageId): void
    {
        $payload = $this->repository->getPayload($telegramMessageId);

        $dto = $this->parser->parse($payload);

        if ($dto === null) {
            $this->repository->markSkipped($telegramMessageId, 'Неподдерживаемый тип сообщения');

            return;
        }

        if ($dto->replyToMessageId !== null && ! $dto->isCommand()) {
            $card = $this->repository->findCardByBotMessageId($dto->chatId, $dto->replyToMessageId);

            if ($card !== null) {
                $this->handleBotReply($dto, $card, $telegramMessageId);

                return;
            }
        }

        if ($dto->mediaGroupId !== null) {
            $existingCardId = $this->repository->findCardIdByMediaGroup($dto->mediaGroupId);

            if ($existingCardId !== null) {
                $this->attachFilesToCard($dto, $existingCardId, $telegramMessageId);
                $this->repository->markProcessed($telegramMessageId);

                return;
            }
        }

        $routingResult = $this->routing->resolve($dto);

        if ($routingResult === null) {
            $this->repository->markSkipped($telegramMessageId, 'Правило маршрутизации не найдено');

            return;
        }

        if ($dto->isCommand() && ! $this->commandHasContent($dto)) {
            $this->telegram->sendMessage(
                $dto->chatId,
                trans('bot.no_content', [], $this->resolveLocale($dto->languageCode)),
            );
            $this->repository->markSkipped($telegramMessageId, 'Команда без контента');

            return;
        }

        $rendered = new RoutingResultDTO(
            listId: $routingResult->listId,
            listName: $routingResult->listName,
            memberIds: $routingResult->memberIds,
            labelIds: $routingResult->labelIds,
            cardTitleTemplate: $this->renderer->render($routingResult->cardTitleTemplate, $dto),
            cardDescriptionTemplate: $this->renderer->render($routingResult->cardDescriptionTemplate, $dto),
        );

        $result = $this->cardCreator->create($dto, $rendered, $telegramMessageId);

        $locale = $this->resolveLocale($dto->languageCode);

        $botMessageId = $this->telegram->sendMessage(
            $dto->chatId,
            $this->buildReplyText($rendered->listName, $result->url, $locale),
            [
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($this->buildDeleteKeyboard($result->shortLink, $locale)),
            ],
        );

        if ($botMessageId !== null) {
            $this->cardLog->setBotMessageId($telegramMessageId, $botMessageId);
        }

        if ($dto->mediaGroupId !== null) {
            $this->attachSkippedGroupParts($dto->mediaGroupId, $result->id, $telegramMessageId);
        }

        $this->repository->markProcessed($telegramMessageId);
    }

    /**
     * Обрабатывает ответ пользователя на сообщение бота с подтверждением создания карточки.
     * Текст → комментарий в Trello. Файлы/фото → прикрепление к карточке.
     */
    private function handleBotReply(TelegramMessageDTO $dto, array $card, int $telegramMessageId): void
    {
        $locale = $this->resolveLocale($dto->languageCode);

        $allFiles = [...$dto->photos, ...$dto->documents];
        $text = $dto->text ?? $dto->caption ?? '';
        $attachmentUrls = [];
        $firstAttachmentResult = null;

        foreach ($allFiles as $fileId) {
            try {
                $file = $this->fileDownloader->download($fileId, $telegramMessageId);
                $result = $this->trello->attachFile($card['card_id'], $file->localPath, $file->mimeType);

                if ($result !== null) {
                    if ($firstAttachmentResult === null) {
                        $firstAttachmentResult = $result;
                    }

                    if ($result->url !== null) {
                        $attachmentUrls[] = $result->url;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Failed to attach reply file to Trello card', [
                    'card_id' => $card['card_id'],
                    'file_id' => $fileId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $commentActionId = null;

        if ($text !== '') {
            $commentText = ! empty($attachmentUrls)
                ? $text."\n\n".implode("\n", $attachmentUrls)
                : $text;

            $commentActionId = $this->trello->addComment($card['card_id'], $commentText);
        }

        $notificationKey = ! empty($allFiles) ? 'bot.file_attached' : 'bot.comment_added';

        $options = ['parse_mode' => 'HTML'];
        $keyboard = $this->buildReplyActionKeyboard($commentActionId, $firstAttachmentResult, $card['card_id'], $locale);

        if ($keyboard !== null) {
            $options['reply_markup'] = json_encode($keyboard);
        }

        $this->telegram->sendMessage(
            $dto->chatId,
            trans($notificationKey, ['url' => $card['card_url']], $locale),
            $options,
        );

        $this->repository->markProcessed($telegramMessageId);
    }

    private function buildReplyActionKeyboard(
        ?string $commentActionId,
        ?AttachmentResult $attachmentResult,
        string $cardId,
        string $locale,
    ): ?array {
        $buttons = [];

        if ($commentActionId !== null) {
            $buttons[] = [
                'text' => trans('bot.delete_comment_button', [], $locale),
                'callback_data' => "delete_comment:{$commentActionId}",
            ];
        }

        if ($attachmentResult !== null) {
            $buttons[] = [
                'text' => trans('bot.delete_attachment_button', [], $locale),
                'callback_data' => "delete_attachment:{$cardId}/{$attachmentResult->id}",
            ];
        }

        if (empty($buttons)) {
            return null;
        }

        return ['inline_keyboard' => [$buttons]];
    }

    private function attachSkippedGroupParts(string $mediaGroupId, string $cardId, int $excludeMessageId): void
    {
        $parts = $this->repository->findSkippedGroupParts($mediaGroupId, $excludeMessageId);

        foreach ($parts as $part) {
            foreach ($part['file_ids'] as $fileId) {
                try {
                    $file = $this->fileDownloader->download($fileId, $part['id']);
                    $this->trello->attachFile($cardId, $file->localPath, $file->mimeType);
                } catch (Throwable $e) {
                    Log::warning('Failed to attach skipped group file to Trello card', [
                        'card_id' => $cardId,
                        'file_id' => $fileId,
                        'message_id' => $part['id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->repository->markProcessed($part['id']);
        }
    }

    private function attachFilesToCard(TelegramMessageDTO $dto, string $cardId, int $telegramMessageId): void
    {
        foreach ([...$dto->photos, ...$dto->documents] as $fileId) {
            try {
                $file = $this->fileDownloader->download($fileId, $telegramMessageId);
                $this->trello->attachFile($cardId, $file->localPath, $file->mimeType);
            } catch (Throwable $e) {
                Log::warning('Failed to attach catching-up file to Trello card', [
                    'card_id' => $cardId,
                    'file_id' => $fileId,
                    'message_id' => $telegramMessageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Есть ли полезный контент для команды: текст после команды или reply_to_message.
     * Текст считается реальным только если содержит хотя бы одну букву (Unicode).
     * Только цифры, символы или их комбинация без букв — не считается контентом.
     * Файлы без текста и без reply также считаются недостаточным контентом.
     */
    private function commandHasContent(TelegramMessageDTO $dto): bool
    {
        $raw = $dto->text ?? $dto->caption ?? '';
        $textAfterCommand = $dto->command !== null
            ? trim(mb_substr($raw, mb_strlen($dto->command)))
            : $raw;

        // Убираем @botname суффикс (/bug@itsell_trello_bot → пустая строка)
        $textAfterCommand = trim(preg_replace('/^@\w+/', '', $textAfterCommand));

        if (preg_match('/\p{L}/u', $textAfterCommand)) {
            return true;
        }

        return $dto->replyToMessage !== null;
    }

    private function buildReplyText(string $listName, string $cardUrl, string $locale): string
    {
        return trans('bot.card_created', [
            'list' => $listName,
            'url' => $cardUrl,
        ], $locale);
    }

    private function buildDeleteKeyboard(string $shortLink, string $locale): array
    {
        return [
            'inline_keyboard' => [[
                [
                    'text' => trans('bot.delete_button', [], $locale),
                    'callback_data' => "delete:{$shortLink}",
                ],
            ]],
        ];
    }

    /**
     * Маппит Telegram language_code на поддерживаемую локаль.
     * Fallback — английский.
     */
    private function resolveLocale(?string $languageCode): string
    {
        return in_array($languageCode, ['en', 'ru', 'uk', 'pl'], strict: true)
            ? $languageCode
            : 'uk';
    }
}
