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
 * При успехе: подтверждает callback и убирает inline-клавиатуру.
 * При ошибке: подтверждает callback с сообщением об ошибке, клавиатуру не трогает.
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
            $this->telegram->answerCallbackQuery(
                $dto->callbackId,
                trans('bot.card_delete_failed', [], $locale),
            );

            return;
        }

        $this->telegram->answerCallbackQuery(
            $dto->callbackId,
            trans('bot.card_deleted', [], $locale),
        );

        $this->telegram->removeInlineKeyboard($dto->chatId, $dto->messageId);
    }

    private function resolveLocale(?string $languageCode): string
    {
        return in_array($languageCode, ['en', 'ru', 'uk', 'pl'], strict: true)
            ? $languageCode
            : 'en';
    }
}
