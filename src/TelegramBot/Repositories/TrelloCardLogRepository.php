<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TrelloCardLog;
use TelegramBot\Contracts\CardLogRepositoryInterface;

/**
 * Eloquent-реализация репозитория логов создания карточек.
 * Единственная точка в пакете, где допустима зависимость на Eloquent-модель —
 * всё остальное взаимодействует только через CardLogRepositoryInterface.
 * Биндинг интерфейса → этой реализации прописывается в AppServiceProvider.
 */
class TrelloCardLogRepository implements CardLogRepositoryInterface
{
    public function logSuccess(
        int $telegramMessageId,
        string $listId,
        string $cardId,
        string $cardUrl,
    ): void {
        TrelloCardLog::query()->create([
            'telegram_message_id' => $telegramMessageId,
            'trello_list_id' => $listId,
            'status' => 'success',
            'trello_card_id' => $cardId,
            'trello_card_url' => $cardUrl,
            'error_message' => null,
        ]);
    }

    public function logError(
        int $telegramMessageId,
        string $listId,
        string $errorMessage,
    ): void {
        TrelloCardLog::query()->create([
            'telegram_message_id' => $telegramMessageId,
            'trello_list_id' => $listId,
            'status' => 'error',
            'trello_card_id' => null,
            'trello_card_url' => null,
            'error_message' => $errorMessage,
        ]);
    }

    public function setBotMessageId(int $telegramMessageId, int $botMessageId): void
    {
        TrelloCardLog::query()
            ->where('telegram_message_id', $telegramMessageId)
            ->where('status', 'success')
            ->update(['bot_message_id' => $botMessageId]);
    }
}
