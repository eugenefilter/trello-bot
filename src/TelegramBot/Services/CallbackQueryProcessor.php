<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use Illuminate\Support\Facades\Log;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CallbackAction;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Throwable;

/**
 * Обрабатывает callback_query от inline-кнопок.
 *
 * Поддерживаемые действия:
 *   - delete:{shortLink} — удаляет карточку Trello
 */
class CallbackQueryProcessor
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TrelloAdapterInterface $trello,
    ) {}

    public function process(TelegramCallbackDTO $dto): void
    {
        $action = CallbackAction::fromData($dto->data);

        if ($action === null) {
            Log::warning('CallbackQueryProcessor: invalid callback_data format', ['data' => $dto->data]);

            return;
        }

        match ($action->action) {
            'delete' => $this->handleDelete($dto, $action->payload),
            default => $this->handleUnknown($dto, $action->action),
        };
    }

    private function handleDelete(TelegramCallbackDTO $dto, string $shortLink): void
    {
        $locale = $this->resolveLocale($dto->languageCode);

        try {
            $this->trello->deleteCard($shortLink);
        } catch (Throwable $e) {
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

    private function handleUnknown(TelegramCallbackDTO $dto, string $action): void
    {
        Log::warning('CallbackQueryProcessor: unknown action', [
            'action' => $action,
            'callback_id' => $dto->callbackId,
        ]);
    }

    private function resolveLocale(?string $languageCode): string
    {
        return match ($languageCode) {
            'ru' => 'ru',
            'uk' => 'uk',
            'pl' => 'pl',
            default => 'en',
        };
    }
}
