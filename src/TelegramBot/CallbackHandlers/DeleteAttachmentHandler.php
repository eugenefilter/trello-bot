<?php

declare(strict_types=1);

namespace TelegramBot\CallbackHandlers;

use Illuminate\Support\Facades\Log;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Throwable;

/**
 * Обрабатывает действие "delete_attachment:{cardId}/{attachmentId}" — удаляет вложение карточки.
 *
 * При успехе: подтверждает callback, убирает inline-клавиатуру, шлёт сообщение в чат.
 * При ошибке: подтверждает callback с текстом ошибки, шлёт сообщение об ошибке, клавиатуру не трогает.
 */
class DeleteAttachmentHandler implements CallbackActionHandlerInterface
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TrelloAdapterInterface $trello,
    ) {}

    public function handle(TelegramCallbackDTO $dto, string $payload): void
    {
        $parts = explode('/', $payload, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            Log::warning('DeleteAttachmentHandler: invalid payload format', ['payload' => $payload]);
            $this->telegram->answerCallbackQuery($dto->callbackId, '');

            return;
        }

        [$cardId, $attachmentId] = $parts;

        $locale = $this->resolveLocale($dto->languageCode);

        try {
            $this->trello->deleteAttachment($cardId, $attachmentId);
        } catch (Throwable) {
            $text = trans('bot.attachment_delete_failed', [], $locale);

            $this->telegram->answerCallbackQuery($dto->callbackId, $text);
            $this->telegram->sendMessage($dto->chatId, $text);

            return;
        }

        $text = trans('bot.attachment_deleted', [], $locale);

        $this->telegram->answerCallbackQuery($dto->callbackId, $text);
        $this->telegram->removeInlineKeyboard($dto->chatId, $dto->messageId);
        $this->telegram->sendMessage($dto->chatId, $text);
    }

    private function resolveLocale(?string $languageCode): string
    {
        return in_array($languageCode, ['en', 'ru', 'uk', 'pl'], strict: true)
            ? $languageCode
            : 'en';
    }
}
