<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Services\CardTemplateRenderer;
use TelegramBot\Services\TelegramUpdateProcessor;
use TelegramBot\Services\TrelloCardCreator;

/**
 * Unit-тест TelegramUpdateProcessor.
 *
 * Все зависимости мокируются — БД и Trello не нужны.
 */
class TelegramUpdateProcessorTest extends TestCase
{
    private MockInterface $repository;

    private MockInterface $parser;

    private MockInterface $routing;

    private MockInterface $cardCreator;

    private MockInterface $telegram;

    private TelegramUpdateProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository  = Mockery::mock(TelegramMessageRepositoryInterface::class);
        $this->parser      = Mockery::mock(UpdateParserInterface::class);
        $this->routing     = Mockery::mock(RoutingEngineInterface::class);
        $this->cardCreator = Mockery::mock(TrelloCardCreator::class);
        $this->telegram    = Mockery::mock(TelegramAdapterInterface::class);

        $this->processor = new TelegramUpdateProcessor(
            $this->repository,
            $this->parser,
            $this->routing,
            $this->cardCreator,
            new CardTemplateRenderer(),
            $this->telegram,
        );
    }

    protected function tearDown(): void
    {
        // Регистрируем Mockery-ожидания как assertions в PHPUnit,
        // чтобы тесты не помечались как Risky.
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Happy path: парсер → routing → создание карточки → ответ пользователю → markProcessed.
     */
    public function test_happy_path_creates_card_and_marks_processed(): void
    {
        $dto     = $this->makeMessageDTO();
        $routing = $this->makeRoutingResult();

        $this->repository->shouldReceive('getPayload')->once()->with(1)->andReturn(['update_id' => 1]);
        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->with($dto)->andReturn($routing);
        $this->cardCreator->shouldReceive('create')
            ->once()
            ->withArgs(function (TelegramMessageDTO $msg, RoutingResultDTO $rendered, int $id) use ($routing) {
                return $id === 1
                    && $rendered->listId === $routing->listId
                    && ! str_contains($rendered->cardTitleTemplate, '{{')
                    && ! str_contains($rendered->cardDescriptionTemplate, '{{');
            })
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));
        $this->telegram->shouldReceive('sendMessage')->once();
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * После успешного создания карточки пользователю отправляется подтверждение
     * с названием списка и ссылкой на карточку.
     */
    public function test_sends_reply_with_list_name_and_card_url_after_creation(): void
    {
        $this->repository->shouldReceive('getPayload')->andReturn([]);
        $this->parser->shouldReceive('parse')->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (string $chatId, string $text) {
                return $chatId === '100'
                    && str_contains($text, 'Backlog')
                    && str_contains($text, 'https://trello.com/c/card-1');
            });

        $this->repository->shouldReceive('markProcessed')->once();

        $this->processor->process(1);
    }

    /**
     * Парсер вернул null — markProcessed вызывается, Trello не вызывается.
     */
    public function test_marks_processed_when_parser_returns_null(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn(null);
        $this->routing->shouldNotReceive('resolve');
        $this->cardCreator->shouldNotReceive('create');
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Routing не нашёл правила — markProcessed вызывается, Trello не вызывается.
     */
    public function test_marks_processed_when_no_routing_match(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn(null);
        $this->cardCreator->shouldNotReceive('create');
        $this->repository->shouldReceive('markProcessed')->once()->with(1);

        $this->processor->process(1);
    }

    /**
     * Trello бросил исключение — markProcessed и sendMessage НЕ вызываются,
     * исключение пробрасывается.
     */
    public function test_does_not_mark_processed_on_trello_exception(): void
    {
        $this->repository->shouldReceive('getPayload')->once()->andReturn([]);
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->andThrow(new TrelloConnectionException('timeout'));
        $this->telegram->shouldNotReceive('sendMessage');
        $this->repository->shouldNotReceive('markProcessed');

        $this->expectException(TrelloConnectionException::class);

        $this->processor->process(1);
    }

    // --- Fixtures ---

    private function makeMessageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'text',
            text: 'Hello world',
            caption: null,
            photos: [],
            documents: [],
            userId: 42,
            chatId: '100',
            chatType: 'private',
            command: null,
            username: 'testuser',
            firstName: 'Test',
            sentAt: new DateTimeImmutable,
        );
    }

    private function makeRoutingResult(): RoutingResultDTO
    {
        return new RoutingResultDTO(
            listId: 'list-abc',
            listName: 'Backlog',
            memberIds: [],
            labelIds: [],
            cardTitleTemplate: '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: '{{text}}',
        );
    }
}
