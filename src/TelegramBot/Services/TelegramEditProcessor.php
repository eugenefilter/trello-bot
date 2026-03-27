<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use Illuminate\Support\Facades\Log;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\TelegramMessageDTO;
use Throwable;

/**
 * Обрабатывает edited_message updates.
 *
 * Порядок обработки:
 *   1. Загружает payload и парсит edited_message → TelegramMessageDTO
 *   2. Ищет исходную карточку Trello по chat_id + message_id
 *   3. Ищет routing rule для рендеринга шаблонов
 *   4. Обновляет название и описание карточки
 *   5. Прикрепляет новые файлы (отсутствующие в telegram_files исходного сообщения)
 *   6. Помечает update как обработанный
 */
class TelegramEditProcessor
{
    public function __construct(
        private readonly TelegramMessageRepositoryInterface $repository,
        private readonly UpdateParserInterface $parser,
        private readonly RoutingEngineInterface $routing,
        private readonly CardTemplateRenderer $renderer,
        private readonly TrelloAdapterInterface $trello,
        private readonly TelegramAdapterInterface $telegram,
        private readonly TelegramFileDownloader $fileDownloader,
        private readonly TelegramFileRepositoryInterface $fileRepository,
    ) {}

    /**
     * @throws Throwable при ошибке Trello — Job уйдёт в retry
     */
    public function process(int $telegramMessageId): void
    {
        $payload = $this->repository->getPayload($telegramMessageId);

        $dto = $this->parser->parseEdit($payload);

        if ($dto === null) {
            $this->repository->markSkipped($telegramMessageId, 'Неподдерживаемый тип edited_message');

            return;
        }

        $messageId = $payload['edited_message']['message_id'] ?? null;

        $original = $messageId !== null
            ? $this->repository->findOriginalCardByMessage($dto->chatId, $messageId)
            : null;

        if ($original === null) {
            if ($dto->replyToMessageId !== null) {
                $card = $this->repository->findCardByBotMessageId($dto->chatId, $dto->replyToMessageId)
                    ?? $this->repository->findCardByLinkedMessage($dto->chatId, $dto->replyToMessageId);

                if ($card !== null) {
                    $this->handleBotReplyEdit($dto, $card, $telegramMessageId);

                    return;
                }
            }

            $this->repository->markSkipped($telegramMessageId, 'Исходная карточка не найдена');

            return;
        }

        $routingResult = $this->routing->resolve($dto);

        if ($routingResult === null) {
            $this->repository->markSkipped($telegramMessageId, 'Правило маршрутизации не найдено');

            return;
        }

        $name = $this->renderer->render($routingResult->cardTitleTemplate, $dto);
        $description = $this->renderer->render($routingResult->cardDescriptionTemplate, $dto);

        $this->trello->updateCard($original['card_id'], $name, $description);

        $this->attachNewFiles($dto, $original['card_id'], $original['telegram_message_id'], $telegramMessageId);

        $locale = $this->resolveLocale($dto->languageCode);

        $this->telegram->sendMessage(
            $dto->chatId,
            trans('bot.card_updated', ['url' => $original['card_url']], $locale),
            ['parse_mode' => 'HTML'],
        );

        $this->repository->markProcessed($telegramMessageId);
    }

    /**
     * Обрабатывает редактирование сообщения-ответа на подтверждение бота.
     * Если есть текст — добавляет комментарий в Trello и уведомляет пользователя.
     * Файлы не трогаем — они были прикреплены при исходной обработке.
     */
    private function handleBotReplyEdit(TelegramMessageDTO $dto, array $card, int $telegramMessageId): void
    {
        $text = $dto->text ?? $dto->caption ?? '';

        if ($text !== '') {
            $locale = $this->resolveLocale($dto->languageCode);

            $commentActionId = $this->trello->addComment($card['card_id'], $text);

            $options = ['parse_mode' => 'HTML'];

            if ($commentActionId !== null) {
                $options['reply_markup'] = json_encode([
                    'inline_keyboard' => [[
                        [
                            'text' => trans('bot.delete_comment_button', [], $locale),
                            'callback_data' => "delete_comment:{$commentActionId}",
                        ],
                    ]],
                ]);
            }

            $this->telegram->sendMessage(
                $dto->chatId,
                trans('bot.comment_added', ['url' => $card['card_url']], $locale),
                $options,
            );
        }

        $this->repository->markProcessed($telegramMessageId);
    }

    private function resolveLocale(?string $languageCode): string
    {
        return in_array($languageCode, ['en', 'ru', 'uk', 'pl'], strict: true)
            ? $languageCode
            : 'uk';
    }

    private function attachNewFiles(
        TelegramMessageDTO $dto,
        string $cardId,
        int $originalMessageId,
        int $editMessageId,
    ): void {
        $knownFileIds = $this->fileRepository->getFileIdsByMessageId($originalMessageId);

        $allFileIds = [
            ...$dto->photos,
            ...$dto->documents,
            ...($dto->replyToMessage?->photos ?? []),
            ...($dto->replyToMessage?->documents ?? []),
        ];

        $newFileIds = array_diff($allFileIds, $knownFileIds);

        foreach ($newFileIds as $fileId) {
            try {
                $file = $this->fileDownloader->download($fileId, $editMessageId);
                $this->trello->attachFile($cardId, $file->localPath, $file->mimeType);
            } catch (Throwable $e) {
                Log::warning('Failed to attach new file on message edit', [
                    'card_id' => $cardId,
                    'file_id' => $fileId,
                    'edit_message_id' => $editMessageId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
