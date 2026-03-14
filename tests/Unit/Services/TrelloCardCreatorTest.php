<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Services\TelegramFileDownloader;
use TelegramBot\Services\TrelloCardCreator;
use Tests\TestCase;

/**
 * Unit-тест TrelloCardCreator.
 *
 * Оба внешних зависимости мокаются через Mockery:
 *   - TrelloAdapterInterface  — не нужен реальный Trello API
 *   - CardLogRepositoryInterface — не нужна БД
 * Тест проверяет только оркестрацию внутри сервиса.
 */
class TrelloCardCreatorTest extends TestCase
{
    private MockInterface $adapter;

    private MockInterface $cardLog;

    private MockInterface $fileDownloader;

    private TrelloCardCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = Mockery::mock(TrelloAdapterInterface::class);
        $this->cardLog = Mockery::mock(CardLogRepositoryInterface::class);
        $this->fileDownloader = Mockery::mock(TelegramFileDownloader::class);
        $this->creator = new TrelloCardCreator($this->adapter, $this->cardLog, $this->fileDownloader);
    }

    /**
     * Заголовок карточки берётся из шаблона routing rule (cardTitleTemplate).
     */
    public function test_creates_card_with_title_from_routing_rule(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->withArgs(function (TrelloCardDTO $dto) {
                return $dto->name === 'Test: Hello world';
            })
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter->shouldNotReceive('addMembersToCard');
        $this->adapter->shouldNotReceive('addLabelsToCard');
        $this->fileDownloader->shouldNotReceive('download');
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(titleTemplate: 'Test: Hello world'),
            telegramMessageId: 1,
        );
    }

    /**
     * Описание карточки берётся из шаблона routing rule (cardDescriptionTemplate).
     */
    public function test_creates_card_with_description_from_routing_rule(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->withArgs(function (TrelloCardDTO $dto) {
                return $dto->description === 'Описание из шаблона';
            })
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter->shouldNotReceive('addMembersToCard');
        $this->adapter->shouldNotReceive('addLabelsToCard');
        $this->fileDownloader->shouldNotReceive('download');
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(descriptionTemplate: 'Описание из шаблона'),
            telegramMessageId: 1,
        );
    }

    /**
     * memberIds передаются в createCard через DTO (не отдельным вызовом addMembersToCard).
     */
    public function test_passes_members_in_create_card_dto(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->withArgs(function (TrelloCardDTO $dto) {
                return $dto->memberIds === ['member-abc'];
            })
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter->shouldNotReceive('addMembersToCard');
        $this->adapter->shouldNotReceive('addLabelsToCard');
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(memberIds: ['member-abc']),
            telegramMessageId: 1,
        );
    }

    /**
     * labelIds передаются в createCard через DTO (не отдельным вызовом addLabelsToCard).
     */
    public function test_passes_labels_in_create_card_dto(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->withArgs(function (TrelloCardDTO $dto) {
                return $dto->labelIds === ['label-xyz'];
            })
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter->shouldNotReceive('addMembersToCard');
        $this->adapter->shouldNotReceive('addLabelsToCard');
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(labelIds: ['label-xyz']),
            telegramMessageId: 1,
        );
    }

    /**
     * При успешном создании вызывается logSuccess с правильными аргументами.
     */
    public function test_calls_log_success_on_successful_creation(): void
    {
        $this->adapter->shouldReceive('createCard')
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->cardLog
            ->shouldReceive('logSuccess')
            ->once()
            ->with(1, 'list-123', 'card-1', 'https://trello.com/c/card-1');

        $this->creator->create($this->messageDTO(), $this->routingDTO(), telegramMessageId: 1);
    }

    /**
     * При ошибке Trello вызывается logError, исключение пробрасывается выше.
     */
    public function test_calls_log_error_and_rethrows_on_trello_exception(): void
    {
        $this->adapter->shouldReceive('createCard')
            ->andThrow(new TrelloConnectionException('Connection refused'));

        $this->cardLog
            ->shouldReceive('logError')
            ->once()
            ->with(1, 'list-123', 'Connection refused');

        $this->expectException(TrelloConnectionException::class);

        $this->creator->create($this->messageDTO(), $this->routingDTO(), telegramMessageId: 1);
    }

    /**
     * Фото из сообщения прикрепляется к созданной карточке.
     */
    public function test_attaches_photo_to_card_on_creation(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->fileDownloader
            ->shouldReceive('download')
            ->once()
            ->with('photo-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));

        $this->adapter
            ->shouldReceive('attachFile')
            ->once()
            ->with('card-1', '/tmp/photo.jpg', 'image/jpeg');

        $this->cardLog->shouldReceive('logSuccess')->once();

        $message = new TelegramMessageDTO(
            messageType: 'text_photo',
            text: null,
            caption: '/bug test',
            photos: ['photo-file-id'],
            documents: [],
            userId: 111111,
            chatId: '222222',
            chatType: 'private',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );

        $this->creator->create($message, $this->routingDTO(), telegramMessageId: 1);
    }

    /**
     * Документ из сообщения прикрепляется к созданной карточке.
     */
    public function test_attaches_document_to_card_on_creation(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->fileDownloader
            ->shouldReceive('download')
            ->once()
            ->with('doc-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/file.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));

        $this->adapter
            ->shouldReceive('attachFile')
            ->once()
            ->with('card-1', '/tmp/file.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->cardLog->shouldReceive('logSuccess')->once();

        $message = new TelegramMessageDTO(
            messageType: 'command',
            text: null,
            caption: '/bug test',
            photos: [],
            documents: ['doc-file-id'],
            userId: 111111,
            chatId: '222222',
            chatType: 'private',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );

        $this->creator->create($message, $this->routingDTO(), telegramMessageId: 1);
    }

    /**
     * Ошибка при загрузке одного файла не роняет создание карточки —
     * карточка всё равно создаётся и помечается успешной.
     */
    public function test_file_download_error_does_not_abort_card_creation(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->fileDownloader
            ->shouldReceive('download')
            ->once()
            ->andThrow(new \RuntimeException('Telegram file not found'));

        // attachFile не должен вызываться, если download упал
        $this->adapter->shouldNotReceive('attachFile');

        // logSuccess всё равно должен вызваться — карточка создана
        $this->cardLog->shouldReceive('logSuccess')->once();

        $message = new TelegramMessageDTO(
            messageType: 'text_photo',
            text: null,
            caption: '/bug test',
            photos: ['photo-file-id'],
            documents: [],
            userId: 111111,
            chatId: '222222',
            chatType: 'private',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );

        // Исключение НЕ пробрасывается
        $this->creator->create($message, $this->routingDTO(), telegramMessageId: 1);
    }

    /**
     * При ошибке первого файла второй всё равно прикрепляется.
     */
    public function test_file_error_on_first_does_not_skip_second(): void
    {
        $this->adapter
            ->shouldReceive('createCard')
            ->once()
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->fileDownloader
            ->shouldReceive('download')
            ->with('bad-file-id', 1)
            ->andThrow(new \RuntimeException('not found'));

        $this->fileDownloader
            ->shouldReceive('download')
            ->with('good-file-id', 1)
            ->andReturn(new DownloadedFile('/tmp/photo.jpg', 'image/jpeg'));

        $this->adapter
            ->shouldReceive('attachFile')
            ->once()
            ->with('card-1', '/tmp/photo.jpg', 'image/jpeg');

        $this->cardLog->shouldReceive('logSuccess')->once();

        $message = new TelegramMessageDTO(
            messageType: 'text_photo',
            text: null,
            caption: '/bug test',
            photos: ['bad-file-id', 'good-file-id'],
            documents: [],
            userId: 111111,
            chatId: '222222',
            chatType: 'private',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );

        $this->creator->create($message, $this->routingDTO(), telegramMessageId: 1);
    }

    // --- Fixtures ---

    private function messageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'text',
            text: 'Hello world',
            caption: null,
            photos: [],
            documents: [],
            userId: 111111,
            chatId: '222222',
            chatType: 'private',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2024-01-01 12:00:00'),
        );
    }

    private function routingDTO(
        string $titleTemplate = 'Test: Hello world',
        string $descriptionTemplate = 'Описание из шаблона',
        array $memberIds = [],
        array $labelIds = [],
    ): RoutingResultDTO {
        return new RoutingResultDTO(
            listId: 'list-123',
            listName: 'Test List',
            memberIds: $memberIds,
            labelIds: $labelIds,
            cardTitleTemplate: $titleTemplate,
            cardDescriptionTemplate: $descriptionTemplate,
        );
    }
}
