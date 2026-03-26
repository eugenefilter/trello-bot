<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\DTOs\ReplyMessageDTO;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Services\CardTemplateRenderer;
use TelegramBot\Services\TelegramEditProcessor;
use TelegramBot\Services\TelegramFileDownloader;
use Tests\TestCase;

/**
 * Unit-тест TelegramEditProcessor.
 *
 * Все зависимости мокируются — БД и Trello не нужны.
 */
class TelegramEditProcessorTest extends TestCase
{
    private MockInterface $repository;

    private MockInterface $parser;

    private MockInterface $routing;

    private MockInterface $trello;

    private MockInterface $telegram;

    private MockInterface $fileDownloader;

    private MockInterface $fileRepository;

    private TelegramEditProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TelegramMessageRepositoryInterface::class);
        $this->parser = Mockery::mock(UpdateParserInterface::class);
        $this->routing = Mockery::mock(RoutingEngineInterface::class);
        $this->trello = Mockery::mock(TrelloAdapterInterface::class);
        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->fileDownloader = Mockery::mock(TelegramFileDownloader::class);
        $this->fileRepository = Mockery::mock(TelegramFileRepositoryInterface::class);

        $this->processor = new TelegramEditProcessor(
            $this->repository,
            $this->parser,
            $this->routing,
            new CardTemplateRenderer,
            $this->trello,
            $this->telegram,
            $this->fileDownloader,
            $this->fileRepository,
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Если parseEdit() вернул null — помечаем как skipped.
     */
    public function test_skips_when_parser_returns_null(): void
    {
        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => []]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn(null);
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Неподдерживаемый тип edited_message');
        $this->trello->shouldNotReceive('updateCard');

        $this->processor->process(1);
    }

    /**
     * Если исходная карточка не найдена — помечаем как skipped.
     */
    public function test_skips_when_no_original_card_found(): void
    {
        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($this->messageDTO());
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn(null);
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Исходная карточка не найдена');
        $this->trello->shouldNotReceive('updateCard');

        $this->processor->process(1);
    }

    /**
     * Если правило маршрутизации не найдено — помечаем как skipped.
     */
    public function test_skips_when_no_routing_rule(): void
    {
        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($this->messageDTO());
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn([
            'telegram_message_id' => 10,
            'card_id' => 'card-abc',
            'card_url' => 'https://trello.com/c/card-abc',
        ]);
        $this->routing->shouldReceive('resolve')->once()->andReturn(null);
        $this->repository->shouldReceive('markSkipped')->once()->with(1, 'Правило маршрутизации не найдено');
        $this->trello->shouldNotReceive('updateCard');
        $this->telegram->shouldNotReceive('sendMessage');

        $this->processor->process(1);
    }

    /**
     * Обновляет карточку с отрендеренными шаблонами, отправляет уведомление и помечает как обработанное.
     */
    public function test_updates_card_with_rendered_templates(): void
    {
        $dto = $this->messageDTO();

        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($dto);
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn([
            'telegram_message_id' => 10,
            'card_id' => 'card-abc',
            'card_url' => 'https://trello.com/c/card-abc',
        ]);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->routingDTO('Название', 'Описание'));
        $this->fileRepository->shouldReceive('getFileIdsByMessageId')->with(10)->andReturn([]);
        $this->trello->shouldReceive('updateCard')->once()->with('card-abc', 'Название', 'Описание');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Уведомление содержит ссылку на карточку.
     */
    public function test_sends_notification_with_card_link(): void
    {
        $dto = $this->messageDTO();

        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($dto);
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn([
            'telegram_message_id' => 10,
            'card_id' => 'card-abc',
            'card_url' => 'https://trello.com/c/card-abc',
        ]);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->routingDTO());
        $this->fileRepository->shouldReceive('getFileIdsByMessageId')->with(10)->andReturn([]);
        $this->trello->shouldReceive('updateCard')->once();

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (string $chatId, string $text) {
                return $chatId === '-1001888188920'
                    && str_contains($text, 'https://trello.com/c/card-abc');
            });

        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Прикрепляет только новые файлы (не присутствующие в telegram_files оригинала).
     */
    public function test_attaches_only_new_files(): void
    {
        $dto = new TelegramMessageDTO(
            messageType: 'command',
            text: '/bug новый текст',
            caption: null,
            photos: ['new-photo-id'],
            documents: [],
            userId: 111111,
            chatId: '-1001888188920',
            chatType: 'supergroup',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2026-03-26 12:00:00'),
        );

        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($dto);
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn([
            'telegram_message_id' => 10,
            'card_id' => 'card-abc',
            'card_url' => 'https://trello.com/c/card-abc',
        ]);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->routingDTO());
        $this->fileRepository->shouldReceive('getFileIdsByMessageId')->with(10)->andReturn(['old-photo-id']);
        $this->trello->shouldReceive('updateCard')->once();
        $this->fileDownloader
            ->shouldReceive('download')
            ->with('new-photo-id', 1)
            ->once()
            ->andReturn(new DownloadedFile('/tmp/new_photo.jpg', 'image/jpeg'));
        $this->trello->shouldReceive('attachFile')->once()->with('card-abc', '/tmp/new_photo.jpg', 'image/jpeg');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Файлы уже известные (в telegram_files оригинала) не прикрепляются повторно.
     */
    public function test_does_not_reattach_already_known_files(): void
    {
        $replyMessage = new ReplyMessageDTO(
            text: null,
            caption: '/bug Съехал текст',
            photos: [],
            documents: ['already-attached-doc-id'],
        );

        $dto = new TelegramMessageDTO(
            messageType: 'command',
            text: '/bug@itsell_trello_bot Отредактировал',
            caption: null,
            photos: [],
            documents: [],
            userId: 111111,
            chatId: '-1001888188920',
            chatType: 'supergroup',
            command: '/bug',
            username: 'testuser',
            firstName: 'Test',
            sentAt: new \DateTimeImmutable('2026-03-26 12:00:00'),
            replyToMessage: $replyMessage,
        );

        $this->repository->shouldReceive('getPayload')->with(1)->andReturn(['edited_message' => ['message_id' => 100]]);
        $this->parser->shouldReceive('parseEdit')->once()->andReturn($dto);
        $this->repository->shouldReceive('findOriginalCardByMessage')->once()->andReturn([
            'telegram_message_id' => 10,
            'card_id' => 'card-abc',
            'card_url' => 'https://trello.com/c/card-abc',
        ]);
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->routingDTO());
        $this->fileRepository->shouldReceive('getFileIdsByMessageId')->with(10)->andReturn(['already-attached-doc-id']);
        $this->trello->shouldReceive('updateCard')->once();
        $this->fileDownloader->shouldNotReceive('download');
        $this->trello->shouldNotReceive('attachFile');
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    // --- Fixtures ---

    private function messageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'command',
            text: '/bug@itsell_trello_bot Отредактировал сообщение для тестов',
            caption: null,
            photos: [],
            documents: [],
            userId: 746276963,
            chatId: '-1001888188920',
            chatType: 'supergroup',
            command: '/bug',
            username: 'eugeneoleinykov',
            firstName: 'Eugene',
            sentAt: new \DateTimeImmutable('2026-03-26 17:17:00'),
        );
    }

    private function routingDTO(
        string $titleTemplate = 'Test title',
        string $descriptionTemplate = 'Test description',
    ): RoutingResultDTO {
        return new RoutingResultDTO(
            listId: 'list-123',
            listName: 'Test List',
            memberIds: [],
            labelIds: [],
            cardTitleTemplate: $titleTemplate,
            cardDescriptionTemplate: $descriptionTemplate,
        );
    }
}
