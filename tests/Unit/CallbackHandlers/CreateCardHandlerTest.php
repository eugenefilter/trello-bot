<?php

declare(strict_types=1);

namespace Tests\Unit\CallbackHandlers;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\CallbackHandlers\CreateCardHandler;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\DTOs\RoutingRuleData;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Services\TelegramUpdateProcessor;
use Tests\TestCase;

class CreateCardHandlerTest extends TestCase
{
    private MockInterface $telegram;

    private MockInterface $processor;

    private MockInterface $ruleRepository;

    private CreateCardHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->processor = Mockery::mock(TelegramUpdateProcessor::class);
        $this->ruleRepository = Mockery::mock(RoutingRuleRepositoryInterface::class);

        $this->handler = new CreateCardHandler(
            telegram: $this->telegram,
            processor: $this->processor,
            ruleRepository: $this->ruleRepository,
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Валидный payload → убирает клавиатуру, создаёт карточку через processor.
     */
    public function test_creates_card_when_rule_found(): void
    {
        $rule = $this->makeRule();
        $dto = $this->makeCallbackDTO();

        $this->ruleRepository->shouldReceive('getRuleById')->once()->with(5)->andReturn($rule);
        $this->telegram->shouldReceive('answerCallbackQuery')->once()->with('cq-1', Mockery::any());
        $this->telegram->shouldReceive('removeInlineKeyboard')->once()->with('100', 42);
        $this->processor->shouldReceive('processWithRule')->once()->with(100, $rule);

        $this->handler->handle($dto, '100_5');
    }

    /**
     * Правило не найдено → только answerCallbackQuery с ошибкой, processor не вызывается.
     */
    public function test_answers_error_when_rule_not_found(): void
    {
        $dto = $this->makeCallbackDTO();

        $this->ruleRepository->shouldReceive('getRuleById')->once()->with(999)->andReturn(null);
        $this->telegram->shouldReceive('answerCallbackQuery')->once()->with('cq-1', Mockery::any());
        $this->telegram->shouldNotReceive('removeInlineKeyboard');
        $this->processor->shouldNotReceive('processWithRule');

        $this->handler->handle($dto, '100_999');
    }

    /**
     * Невалидный формат payload → только answerCallbackQuery с ошибкой.
     */
    public function test_answers_error_on_invalid_payload_format(): void
    {
        $dto = $this->makeCallbackDTO();

        $this->ruleRepository->shouldNotReceive('getRuleById');
        $this->telegram->shouldReceive('answerCallbackQuery')->once()->with('cq-1', Mockery::any());
        $this->telegram->shouldNotReceive('removeInlineKeyboard');
        $this->processor->shouldNotReceive('processWithRule');

        $this->handler->handle($dto, 'invalid-no-underscore');
    }

    private function makeCallbackDTO(): TelegramCallbackDTO
    {
        return new TelegramCallbackDTO(
            callbackId: 'cq-1',
            chatId: '100',
            messageId: 42,
            data: 'create_card:100_5',
            languageCode: 'en',
        );
    }

    private function makeRule(): RoutingRuleData
    {
        return new RoutingRuleData(
            id: 5,
            chatId: null,
            chatType: null,
            command: null,
            hasPhoto: null,
            isForwarded: true,
            trelloListId: 'list-abc',
            listName: 'Backlog',
            labelIds: [],
            memberIds: [],
            cardTitleTemplate: '{{first_name}}: {{text_preview}}',
            cardDescriptionTemplate: '{{text}}',
            priority: 0,
        );
    }
}
