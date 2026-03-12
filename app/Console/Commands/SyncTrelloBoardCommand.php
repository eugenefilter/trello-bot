<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TrelloConnection;
use App\Models\TrelloLabel;
use App\Models\TrelloList;
use App\Models\TrelloMember;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use TelegramBot\Adapters\TrelloAdapter;

/**
 * Синхронизирует справочники Trello из API в локальную БД.
 * Синхронизирует три коллекции для указанного подключения:
 *   - trello_lists   (колонки доски)
 *   - trello_labels  (метки)
 *   - trello_members (участники)
 * Алгоритм для каждой коллекции:
 *   1. Получить актуальный список из Trello API
 *   2. Создать или обновить запись (updateOrCreate по trello_*_id)
 *   3. Пометить записи, отсутствующие в ответе API, как is_active = false
 * Команда идемпотентна: повторный запуск не создаёт дублей.
 * Использование:
 *   php artisan trello:sync {connection_id}
 */
class SyncTrelloBoardCommand extends Command
{
    protected $signature = 'trello:sync {connection_id : ID записи в таблице trello_connections}';

    protected $description = 'Синхронизирует справочники Trello (списки, метки, участники) из API';

    public function handle(HttpFactory $http): int
    {
        $connectionId = (int) $this->argument('connection_id');

        $connection = TrelloConnection::query()->find($connectionId);

        if ($connection === null) {
            $this->error("Подключение #{$connectionId} не найдено в базе данных.");

            return self::FAILURE;
        }

        $adapter = new TrelloAdapter(
            http: $http,
            apiKey: $connection->api_key,
            apiToken: $connection->api_token,
        );

        $this->syncLists($connection, $adapter);
        $this->syncLabels($connection, $adapter);
        $this->syncMembers($connection, $adapter);

        $this->info("Синхронизация завершена для подключения #{$connectionId} (доска: {$connection->board_id}).");

        return self::SUCCESS;
    }

    private function syncLists(TrelloConnection $connection, TrelloAdapter $adapter): void
    {
        $items = $adapter->getBoardLists($connection->board_id);
        $seenIds = [];

        foreach ($items as $item) {
            TrelloList::query()->updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'trello_list_id' => $item['id'],
                ],
                [
                    'board_id' => $connection->board_id,
                    'name' => $item['name'],
                    'is_active' => true,
                ],
            );

            $seenIds[] = $item['id'];
        }

        TrelloList::query()->where('connection_id', $connection->id)
            ->whereNotIn('trello_list_id', $seenIds)
            ->update(['is_active' => false]);

        $this->line('  Списки: '.count($items).' синхронизировано.');
    }

    private function syncLabels(TrelloConnection $connection, TrelloAdapter $adapter): void
    {
        $items = $adapter->getBoardLabels($connection->board_id);
        $seenIds = [];

        foreach ($items as $item) {
            TrelloLabel::query()->updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'trello_label_id' => $item['id'],
                ],
                [
                    'board_id' => $connection->board_id,
                    'name' => $item['name'] !== '' ? $item['name'] : null,
                    'color' => $item['color'] ?? null,
                    'is_active' => true,
                ],
            );

            $seenIds[] = $item['id'];
        }

        TrelloLabel::query()->where('connection_id', $connection->id)
            ->whereNotIn('trello_label_id', $seenIds)
            ->update(['is_active' => false]);

        $this->line('  Метки: '.count($items).' синхронизировано.');
    }

    private function syncMembers(TrelloConnection $connection, TrelloAdapter $adapter): void
    {
        $items = $adapter->getBoardMembers($connection->board_id);
        $seenIds = [];

        foreach ($items as $item) {
            TrelloMember::query()->updateOrCreate(
                [
                    'connection_id' => $connection->id,
                    'trello_member_id' => $item['id'],
                ],
                [
                    'username' => $item['username'],
                    'full_name' => $item['fullName'],
                    'is_active' => true,
                ],
            );

            $seenIds[] = $item['id'];
        }

        TrelloMember::query()->where('connection_id', $connection->id)
            ->whereNotIn('trello_member_id', $seenIds)
            ->update(['is_active' => false]);

        $this->line('  Участники: '.count($items).' синхронизировано.');
    }
}
