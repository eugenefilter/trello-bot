<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\TelegramMessage;
use App\Models\TrelloCardLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use Tests\TestCase;

/**
 * Проверяет, что при сохранении сообщения с фото
 * создаются соответствующие записи в telegram_files.
 */
class EloquentTelegramMessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TelegramMessageRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TelegramMessageRepositoryInterface::class);
    }

    public function test_saves_largest_photo_to_telegram_files_when_message_has_photos(): void
    {
        $this->repository->firstOrCreate($this->photoUpdate());

        $message = TelegramMessage::first();

        $this->assertDatabaseCount('telegram_files', 1);
        $this->assertDatabaseHas('telegram_files', [
            'telegram_message_id' => $message->id,
            'file_id' => 'largest-file-id',
            'file_unique_id' => 'largest-unique-id',
            'file_type' => 'photo',
            'local_path' => null,
        ]);
    }

    public function test_does_not_save_files_for_text_only_message(): void
    {
        $this->repository->firstOrCreate($this->textUpdate());

        $this->assertDatabaseCount('telegram_files', 0);
    }

    public function test_does_not_duplicate_files_on_repeated_update(): void
    {
        $update = $this->photoUpdate();

        $this->repository->firstOrCreate($update);
        $this->repository->firstOrCreate($update);

        $this->assertDatabaseCount('telegram_files', 1);
    }

    public function test_saves_media_group_id_when_present(): void
    {
        $this->repository->firstOrCreate($this->groupUpdate());

        $this->assertDatabaseHas('telegram_messages', [
            'update_id' => 300,
            'media_group_id' => 'group-abc-123',
        ]);
    }

    public function test_saves_null_media_group_id_for_regular_message(): void
    {
        $this->repository->firstOrCreate($this->textUpdate());

        $message = TelegramMessage::first();

        $this->assertNull($message->media_group_id);
    }

    public function test_find_card_id_by_media_group_returns_card_id_when_exists(): void
    {
        $result = $this->repository->firstOrCreate($this->groupUpdate());

        TrelloCardLog::create([
            'telegram_message_id' => $result['id'],
            'trello_card_id' => 'trello-card-abc',
            'trello_card_url' => 'https://trello.com/c/abc',
            'trello_list_id' => 'list-1',
            'status' => 'success',
        ]);

        $cardId = $this->repository->findCardIdByMediaGroup('group-abc-123');

        $this->assertSame('trello-card-abc', $cardId);
    }

    public function test_find_card_id_by_media_group_returns_null_when_no_card_yet(): void
    {
        $this->repository->firstOrCreate($this->groupUpdate());

        $cardId = $this->repository->findCardIdByMediaGroup('group-abc-123');

        $this->assertNull($cardId);
    }

    public function test_find_card_id_by_media_group_returns_null_for_unknown_group(): void
    {
        $cardId = $this->repository->findCardIdByMediaGroup('nonexistent-group');

        $this->assertNull($cardId);
    }

    public function test_find_skipped_group_parts_returns_parts_with_file_ids(): void
    {
        // Сохраняем первую часть группы (фото без caption — будет skipped)
        $part1 = $this->repository->firstOrCreate($this->groupUpdate(updateId: 301, fileId: 'file-part1'));
        $this->repository->markSkipped($part1['id'], 'Правило маршрутизации не найдено');

        // Сохраняем вторую часть группы (тоже skipped)
        $part2 = $this->repository->firstOrCreate($this->groupUpdate(updateId: 302, fileId: 'file-part2'));
        $this->repository->markSkipped($part2['id'], 'Правило маршрутизации не найдено');

        // Главное сообщение (с caption) — excludeMessageId
        $main = $this->repository->firstOrCreate($this->groupUpdate(updateId: 300, fileId: 'file-main'));

        $parts = $this->repository->findSkippedGroupParts('group-abc-123', $main['id']);

        $this->assertCount(2, $parts);

        $allFileIds = array_merge(...array_column($parts, 'file_ids'));
        $this->assertContains('file-part1', $allFileIds);
        $this->assertContains('file-part2', $allFileIds);
        $this->assertNotContains('file-main', $allFileIds);
    }

    public function test_find_skipped_group_parts_excludes_main_message(): void
    {
        $main = $this->repository->firstOrCreate($this->groupUpdate(updateId: 300, fileId: 'file-main'));

        $parts = $this->repository->findSkippedGroupParts('group-abc-123', $main['id']);

        $this->assertCount(0, $parts);
    }

    public function test_find_skipped_group_parts_excludes_already_processed(): void
    {
        $part = $this->repository->firstOrCreate($this->groupUpdate(updateId: 301, fileId: 'file-part'));
        $this->repository->markProcessed($part['id']);

        $main = $this->repository->firstOrCreate($this->groupUpdate(updateId: 300, fileId: 'file-main'));

        $parts = $this->repository->findSkippedGroupParts('group-abc-123', $main['id']);

        $this->assertCount(0, $parts);
    }

    // --- Fixtures ---

    private function photoUpdate(): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 1, 'username' => 'user', 'first_name' => 'Test'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'date' => 1700000000,
                'caption' => '/bug some text',
                'photo' => [
                    [
                        'file_id' => 'small-file-id',
                        'file_unique_id' => 'small-unique-id',
                        'file_size' => 1000,
                        'width' => 90,
                        'height' => 51,
                    ],
                    [
                        'file_id' => 'largest-file-id',
                        'file_unique_id' => 'largest-unique-id',
                        'file_size' => 76000,
                        'width' => 1179,
                        'height' => 663,
                    ],
                ],
            ],
        ];
    }

    private function textUpdate(): array
    {
        return [
            'update_id' => 200,
            'message' => [
                'message_id' => 2,
                'from' => ['id' => 1, 'username' => 'user', 'first_name' => 'Test'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'date' => 1700000000,
                'text' => 'Hello',
            ],
        ];
    }

    private function groupUpdate(int $updateId = 300, string $fileId = 'group-file-id'): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 1, 'username' => 'user', 'first_name' => 'Test'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'date' => 1700000000,
                'caption' => '/bug group photo',
                'media_group_id' => 'group-abc-123',
                'photo' => [
                    [
                        'file_id' => $fileId,
                        'file_unique_id' => $fileId.'-unique',
                        'file_size' => 50000,
                        'width' => 800,
                        'height' => 600,
                    ],
                ],
            ],
        ];
    }
}
