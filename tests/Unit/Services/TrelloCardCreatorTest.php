<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
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
    private TrelloCardCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = Mockery::mock(TrelloAdapterInterface::class);
        $this->cardLog = Mockery::mock(CardLogRepositoryInterface::class);
        $this->creator = new TrelloCardCreator($this->adapter, $this->cardLog);
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

        $this->adapter->shouldReceive('addMembersToCard')->once();
        $this->adapter->shouldReceive('addLabelsToCard')->once();
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

        $this->adapter->shouldReceive('addMembersToCard')->once();
        $this->adapter->shouldReceive('addLabelsToCard')->once();
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(descriptionTemplate: 'Описание из шаблона'),
            telegramMessageId: 1,
        );
    }

    /**
     * После создания карточки вызывается addMembersToCard с ID из routing rule.
     */
    public function test_adds_members_after_card_creation(): void
    {
        $this->adapter->shouldReceive('createCard')
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter
            ->shouldReceive('addMembersToCard')
            ->once()
            ->with('card-1', ['member-abc']);

        $this->adapter->shouldReceive('addLabelsToCard')->once();
        $this->cardLog->shouldReceive('logSuccess')->once();

        $this->creator->create(
            $this->messageDTO(),
            $this->routingDTO(memberIds: ['member-abc']),
            telegramMessageId: 1,
        );
    }

    /**
     * После создания карточки вызывается addLabelsToCard с ID из routing rule.
     */
    public function test_adds_labels_after_card_creation(): void
    {
        $this->adapter->shouldReceive('createCard')
            ->andReturn(new CreatedCardResult('card-1', 'https://trello.com/c/card-1'));

        $this->adapter->shouldReceive('addMembersToCard')->once();

        $this->adapter
            ->shouldReceive('addLabelsToCard')
            ->once()
            ->with('card-1', ['label-xyz']);

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
        $this->adapter->shouldReceive('addMembersToCard')->once();
        $this->adapter->shouldReceive('addLabelsToCard')->once();

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

    // --- Fixtures ---

    private function messageDTO(): TelegramMessageDTO
    {
        return new TelegramMessageDTO(
            messageType: 'text',
            text:        'Hello world',
            caption:     null,
            photos:      [],
            documents:   [],
            userId:      111111,
            chatId:      '222222',
            chatType:    'private',
            command:     null,
            username:    'testuser',
            firstName:   'Test',
            sentAt:      new \DateTimeImmutable('2024-01-01 12:00:00'),
        );
    }

    private function routingDTO(
        string $titleTemplate       = 'Test: Hello world',
        string $descriptionTemplate = 'Описание из шаблона',
        array  $memberIds           = [],
        array  $labelIds            = [],
    ): RoutingResultDTO {
        return new RoutingResultDTO(
            listId:                  'list-123',
            listName:                'Test List',
            memberIds:               $memberIds,
            labelIds:                $labelIds,
            cardTitleTemplate:       $titleTemplate,
            cardDescriptionTemplate: $descriptionTemplate,
        );
    }
}
