<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\TelegramMessage;
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
}
