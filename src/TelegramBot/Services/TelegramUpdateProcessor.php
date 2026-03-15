<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use Illuminate\Support\Facades\Log;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use Throwable;

/**
 * Оркестрирует обработку одного Telegram update.
 *
 * Порядок для обычного сообщения:
 *   1. Загружает payload из репозитория
 *   2. Парсит payload → TelegramMessageDTO (null = неподдерживаемый тип)
 *   3. Ищет routing rule → RoutingResultDTO (null = нет подходящего правила)
 *   4. Рендерит шаблоны карточки через CardTemplateRenderer
 *   5. Создаёт карточку в Trello
 *   6. Отправляет подтверждение пользователю через Telegram
 *   7. Помечает сообщение как обработанное
 *
 * Для "догоняющего" update медиагруппы (карточка уже создана):
 *   1-2. Парсит payload
 *   3. Находит существующую карточку по media_group_id
 *   4. Скачивает и прикрепляет файлы к существующей карточке
 *   5. Помечает сообщение как обработанное (без reply и без новой карточки)
 *
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
                '⚠️ Не удалось создать карточку. Добавьте описание или ответьте на сообщение с контентом.',
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

        $this->telegram->sendMessage(
            $dto->chatId,
            $this->buildReplyText($rendered->listName, $result->url),
            ['parse_mode' => 'HTML'],
        );

        if ($dto->mediaGroupId !== null) {
            $this->attachSkippedGroupParts($dto->mediaGroupId, $result->id, $telegramMessageId);
        }

        $this->repository->markProcessed($telegramMessageId);
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

        if (preg_match('/\p{L}/u', $textAfterCommand)) {
            return true;
        }

        return $dto->replyToMessage !== null;
    }

    private function buildReplyText(string $listName, string $cardUrl): string
    {
        return "✅ Карточка создана\n\nКолонка: {$listName}\n<a href=\"{$cardUrl}\">Ссылка на карточку</a>";
    }
}
