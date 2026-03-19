<?php

declare(strict_types=1);

namespace Tests\Feature\MediaGroup;

use App\Models\TrelloCardLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\Services\TelegramFileDownloader;
use TelegramBot\Services\TelegramUpdateProcessor;
use Tests\TestCase;

/**
 * Интеграционный Feature-тест обработки медиагруппы.
 *
 * Использует реальную БД — проверяет полный сценарий:
 *   1. Часть группы без caption приходит первой → skipped (нет команды)
 *   2. Вторая часть без caption → тоже skipped
 *   3. Главное сообщение с caption → создаёт карточку
 *      и retroactively прикрепляет файлы из пунктов 1 и 2
 *
 * Внешние адаптеры (Trello, Telegram, FileDownloader) мокируются —
 * тест проверяет только координацию через БД.
 */
class MediaGroupProcessingTest extends TestCase
{
    use RefreshDatabase;

    private TelegramMessageRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TelegramMessageRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Сценарий "caption пришёл последним":
     * сначала два фото без команды (skipped), потом фото с /bug → retroactive attach.
     */
    public function test_retroactively_attaches_skipped_parts_when_main_arrives(): void
    {
        // 1. Две части группы без caption — сохраняем и помечаем skipped
        $part1 = $this->repository->firstOrCreate($this->photoUpdate(updateId: 101, fileId: 'file-part1'));
        $this->repository->markSkipped($part1['id'], 'Правило маршрутизации не найдено');

        $part2 = $this->repository->firstOrCreate($this->photoUpdate(updateId: 102, fileId: 'file-part2'));
        $this->repository->markSkipped($part2['id'], 'Правило маршрутизации не найдено');

        // 2. Главное сообщение с caption
        $main = $this->repository->firstOrCreate($this->mainUpdate(updateId: 103, fileId: 'file-main'));

        // 3. Мокируем внешние сервисы
        $routing = Mockery::mock(RoutingEngineInterface::class);
        $routing->shouldReceive('resolve')->andReturn($this->routingResult());
        $this->app->instance(RoutingEngineInterface::class, $routing);

        $trello = Mockery::mock(TrelloAdapterInterface::class);
        $trello->shouldReceive('createCard')
            ->once()
            ->andReturn(new CreatedCardResult('card-abc', 'AbCd1234', 'https://trello.com/c/abc'));
        // Ожидаем 3 вызова attachFile: main + part1 + part2
        $trello->shouldReceive('attachFile')->times(3);
        $this->app->instance(TrelloAdapterInterface::class, $trello);

        $telegram = Mockery::mock(TelegramAdapterInterface::class);
        $telegram->shouldReceive('sendMessageWithKeyboard')->once();
        $this->app->instance(TelegramAdapterInterface::class, $telegram);

        $downloader = Mockery::mock(TelegramFileDownloader::class);
        $downloader->shouldReceive('download')
            ->times(3)
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));
        $this->app->instance(TelegramFileDownloader::class, $downloader);

        // 4. Запускаем обработку главного сообщения
        $processor = app(TelegramUpdateProcessor::class);
        $processor->process($main['id']);

        // 5. Все три сообщения теперь success
        $this->assertDatabaseHas('telegram_messages', [
            'id' => $part1['id'],
            'processing_status' => 'success',
        ]);
        $this->assertDatabaseHas('telegram_messages', [
            'id' => $part2['id'],
            'processing_status' => 'success',
        ]);
        $this->assertDatabaseHas('telegram_messages', [
            'id' => $main['id'],
            'processing_status' => 'success',
        ]);
    }

    /**
     * Сценарий "догоняющий update": карточка уже есть → файл прикрепляется без создания новой.
     */
    public function test_catching_up_update_attaches_to_existing_card(): void
    {
        // 1. Сохраняем главное сообщение и создаём карточку через лог
        $main = $this->repository->firstOrCreate($this->mainUpdate(updateId: 200, fileId: 'file-main'));
        TrelloCardLog::create([
            'telegram_message_id' => $main['id'],
            'trello_card_id' => 'existing-card',
            'trello_card_url' => 'https://trello.com/c/existing-card',
            'trello_list_id' => 'list-1',
            'status' => 'success',
        ]);
        $this->repository->markProcessed($main['id']);

        // 2. Новый догоняющий update той же группы
        $late = $this->repository->firstOrCreate($this->photoUpdate(updateId: 201, fileId: 'file-late'));

        // 3. Мокируем внешние сервисы
        $trello = Mockery::mock(TrelloAdapterInterface::class);
        $trello->shouldReceive('attachFile')
            ->once()
            ->with('existing-card', '/tmp/photo.jpg', 'image/jpeg');
        $trello->shouldNotReceive('createCard');
        $this->app->instance(TrelloAdapterInterface::class, $trello);

        $telegram = Mockery::mock(TelegramAdapterInterface::class);
        $telegram->shouldNotReceive('sendMessage');
        $this->app->instance(TelegramAdapterInterface::class, $telegram);

        $downloader = Mockery::mock(TelegramFileDownloader::class);
        $downloader->shouldReceive('download')
            ->once()
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));
        $this->app->instance(TelegramFileDownloader::class, $downloader);

        // 4. Обрабатываем догоняющий update
        $processor = app(TelegramUpdateProcessor::class);
        $processor->process($late['id']);

        // 5. Догоняющий update помечен как success
        $this->assertDatabaseHas('telegram_messages', [
            'id' => $late['id'],
            'processing_status' => 'success',
        ]);
    }

    /**
     * Группа без caption ни у одного update → все skipped.
     */
    public function test_group_without_caption_marks_all_skipped(): void
    {
        $part1 = $this->repository->firstOrCreate($this->photoUpdate(updateId: 301, fileId: 'file-1'));
        $part2 = $this->repository->firstOrCreate($this->photoUpdate(updateId: 302, fileId: 'file-2'));

        $routing = Mockery::mock(RoutingEngineInterface::class);
        $routing->shouldReceive('resolve')->twice()->andReturn(null);
        $this->app->instance(RoutingEngineInterface::class, $routing);

        $this->app->instance(TrelloAdapterInterface::class, Mockery::mock(TrelloAdapterInterface::class));
        $this->app->instance(TelegramAdapterInterface::class, Mockery::mock(TelegramAdapterInterface::class));
        $this->app->instance(TelegramFileDownloader::class, Mockery::mock(TelegramFileDownloader::class));

        $processor = app(TelegramUpdateProcessor::class);
        $processor->process($part1['id']);
        $processor->process($part2['id']);

        $this->assertDatabaseHas('telegram_messages', ['id' => $part1['id'], 'processing_status' => 'skipped']);
        $this->assertDatabaseHas('telegram_messages', ['id' => $part2['id'], 'processing_status' => 'skipped']);
    }

    // --- Fixtures ---

    private function photoUpdate(int $updateId, string $fileId): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 1, 'username' => 'user', 'first_name' => 'Test'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'date' => 1700000000,
                'media_group_id' => 'test-group-id',
                'photo' => [
                    ['file_id' => $fileId, 'file_unique_id' => $fileId.'-u', 'file_size' => 50000, 'width' => 800, 'height' => 600],
                ],
            ],
        ];
    }

    private function mainUpdate(int $updateId, string $fileId): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'from' => ['id' => 1, 'username' => 'user', 'first_name' => 'Test'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'date' => 1700000000,
                'caption' => '/bug Test message',
                'caption_entities' => [['type' => 'bot_command', 'offset' => 0, 'length' => 4]],
                'media_group_id' => 'test-group-id',
                'photo' => [
                    ['file_id' => $fileId, 'file_unique_id' => $fileId.'-u', 'file_size' => 50000, 'width' => 800, 'height' => 600],
                ],
            ],
        ];
    }

    private function routingResult(): RoutingResultDTO
    {
        return new RoutingResultDTO(
            listId: 'list-abc',
            listName: 'Backlog',
            memberIds: [],
            labelIds: [],
            cardTitleTemplate: '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: 'От: {{first_name}}',
        );
    }
}
