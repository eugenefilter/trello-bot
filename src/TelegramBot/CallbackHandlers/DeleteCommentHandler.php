<?php

declare(strict_types=1);

namespace TelegramBot\CallbackHandlers;

use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Throwable;

/**
 * Обрабатывает действие "delete_comment:{actionId}" — удаляет комментарий Trello.
 *
 * При успехе: подтверждает callback, удаляет сообщение из чата, шлёт подтверждение.
 * При ошибке: подтверждает callback с текстом ошибки, шлёт сообщение об ошибке, сообщение не удаляет.
 */
class DeleteCommentHandler implements CallbackActionHandlerInterface
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TrelloAdapterInterface $trello,
    ) {}

    public function handle(TelegramCallbackDTO $dto, string $payload): void
    {
        $locale = $this->resolveLocale($dto->languageCode);

        try {
            $this->trello->deleteComment($payload);
        } catch (Throwable) {
            $text = trans('bot.comment_delete_failed', [], $locale);

            $this->telegram->answerCallbackQuery($dto->callbackId, $text);
            $this->telegram->sendMessage($dto->chatId, $text);

            return;
        }

        $text = trans('bot.comment_deleted', [], $locale);

        $this->telegram->answerCallbackQuery($dto->callbackId, $text);
        $this->telegram->deleteMessage($dto->chatId, $dto->messageId);
        $this->telegram->sendMessage($dto->chatId, $text);
    }

    private function resolveLocale(?string $languageCode): string
    {
        return in_array($languageCode, ['en', 'ru', 'uk', 'pl'], strict: true)
            ? $languageCode
            : 'en';
    }
}
