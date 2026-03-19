<?php

declare(strict_types=1);

namespace TelegramBot\CallbackHandlers;

use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Throwable;

/**
 * Обрабатывает действие "delete:{shortLink}" — удаляет карточку Trello.
 *
 * При успехе: подтверждает callback, убирает inline-клавиатуру, шлёт сообщение в чат.
 * При ошибке: подтверждает callback с текстом ошибки, шлёт сообщение об ошибке, клавиатуру не трогает.
 */
class DeleteCardHandler implements CallbackActionHandlerInterface
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TrelloAdapterInterface $trello,
    ) {}

    public function handle(TelegramCallbackDTO $dto, string $payload): void
    {
        $locale = $this->resolveLocale($dto->languageCode);

        try {
            $this->trello->deleteCard($payload);
        } catch (Throwable) {
            $text = trans('bot.card_delete_failed', [], $locale);

            $this->telegram->answerCallbackQuery($dto->callbackId, $text);
            $this->telegram->sendMessage($dto->chatId, $text);

            return;
        }

        $text = trans('bot.card_deleted', [], $locale);

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
