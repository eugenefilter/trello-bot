<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\TelegramMessage;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Services\TrelloCardCreator;
use Tests\TestCase;

/**
 * Feature-тест ProcessTelegramUpdateJob.
 *
 * Тест требует БД (создаёт TelegramMessage, проверяет processed_at).
 * Все зависимости Job мокируются через Mockery.
 * Job вызывается напрямую через handle() — без очереди.
 */
class ProcessTelegramUpdateJobTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $parser;
    private MockInterface $routing;
    private MockInterface $cardCreator;
    private TelegramMessage $telegramMessage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser      = Mockery::mock(UpdateParserInterface::class);
        $this->routing     = Mockery::mock(RoutingEngineInterface::class);
        $this->cardCreator = Mockery::mock(TrelloCardCreator::class);

        $this->telegramMessage = TelegramMessage::create([
            'update_id'    => 123456,
            'message_id'   => 1,
            'chat_id'      => 100,
            'chat_type'    => 'private',
            'user_id'      => 42,
            'username'     => 'testuser',
            'first_name'   => 'Test',
            'text'         => 'Hello world',
            'caption'      => null,
            'payload_json' => ['update_id' => 123456, 'message' => []],
            'received_at'  => now(),
        ]);
    }

    /**
     * Happy path: парсер возвращает DTO, routing находит правило,
     * карточка создаётся, processed_at проставляется.
     */
    public function test_happy_path_creates_card_and_marks_processed(): void
    {
        $dto     = $this->makeMessageDTO();
        $routing = $this->makeRoutingResult();

        $this->parser->shouldReceive('parse')->once()->andReturn($dto);
        $this->routing->shouldReceive('resolve')->once()->with($dto)->andReturn($routing);
        $this->cardCreator->shouldReceive('create')->once()
            ->with($dto, $routing, $this->telegramMessage->id)
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->runJob();

        $this->assertNotNull(
            TelegramMessage::find($this->telegramMessage->id)?->processed_at,
            'processed_at должен быть проставлен после успешной обработки',
        );
    }

    /**
     * Парсер вернул null (channel_post, edited_message и т.д.).
     * Job помечает сообщение как обработанное, Trello не вызывается.
     */
    public function test_marks_processed_and_skips_card_when_parser_returns_null(): void
    {
        $this->parser->shouldReceive('parse')->once()->andReturn(null);
        $this->routing->shouldNotReceive('resolve');
        $this->cardCreator->shouldNotReceive('create');

        $this->runJob();

        $this->assertNotNull(TelegramMessage::find($this->telegramMessage->id)?->processed_at);
    }

    /**
     * Routing не нашёл подходящего правила.
     * Job помечает сообщение как обработанное, Trello не вызывается.
     */
    public function test_marks_processed_and_skips_card_when_no_routing_match(): void
    {
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn(null);
        $this->cardCreator->shouldNotReceive('create');

        $this->runJob();

        $this->assertNotNull(TelegramMessage::find($this->telegramMessage->id)?->processed_at);
    }

    /**
     * Trello вернул ошибку (сетевая ошибка и т.д.).
     * processed_at НЕ проставляется — Job уйдёт в retry.
     * Исключение пробрасывается наверх.
     */
    public function test_does_not_mark_processed_on_trello_exception(): void
    {
        $this->parser->shouldReceive('parse')->once()->andReturn($this->makeMessageDTO());
        $this->routing->shouldReceive('resolve')->once()->andReturn($this->makeRoutingResult());
        $this->cardCreator->shouldReceive('create')->andThrow(new TrelloConnectionException('timeout'));

        $this->expectException(TrelloConnectionException::class);

        $this->runJob();

        $this->assertNull(
            TelegramMessage::find($this->telegramMessage->id)?->processed_at,
            'processed_at не должен быть проставлен при ошибке Trello',
        );
    }

    // --- Вспомогательные методы ---

    private function runJob(): void
    {
        (new ProcessTelegramUpdateJob($this->telegramMessage->id))
            ->handle($this->parser, $this->routing, $this->cardCreator);
    }

    private function makeMessageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'text',
            text:        'Hello world',
            caption:     null,
            photos:      [],
            documents:   [],
            userId:      42,
            chatId:      '100',
            chatType:    'private',
            command:     null,
            username:    'testuser',
            firstName:   'Test',
            sentAt:      new DateTimeImmutable(),
        );
    }

    private function makeRoutingResult(): RoutingResultDTO
    {
        return new RoutingResultDTO(
            listId:                  'list-abc',
            memberIds:               [],
            labelIds:                [],
            cardTitleTemplate:       '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: '{{text}}',
        );
    }
}
