<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Services\CallbackQueryProcessor;
use Tests\TestCase;

/**
 * Unit-тест CallbackQueryProcessor.
 */
class CallbackQueryProcessorTest extends TestCase
{
    private MockInterface $telegram;

    private MockInterface $trello;

    private CallbackQueryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->trello = Mockery::mock(TrelloAdapterInterface::class);

        $this->processor = new CallbackQueryProcessor(
            telegram: $this->telegram,
            trello: $this->trello,
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * delete action: удаляет карточку, отвечает на callback и убирает кнопку.
     */
    public function test_delete_action_deletes_card_and_removes_keyboard(): void
    {
        $dto = $this->makeCallbackDTO('delete:AbCd1234');

        $this->trello->shouldReceive('deleteCard')->once()->with('AbCd1234');
        $this->telegram->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn ($id, $text) => $id === 'cq-1' && str_contains($text, 'удалена'));
        $this->telegram->shouldReceive('removeInlineKeyboard')->once()->with('100', 42);

        $this->processor->process($dto);
    }

    /**
     * Ошибка Trello API — отвечаем на callback с текстом ошибки, кнопку не убираем.
     */
    public function test_trello_error_answers_callback_with_error_text(): void
    {
        $dto = $this->makeCallbackDTO('delete:AbCd1234');

        $this->trello->shouldReceive('deleteCard')->andThrow(new TrelloConnectionException('timeout'));
        $this->telegram->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn ($id, $text) => str_contains($text, 'Ошибка'));
        $this->telegram->shouldNotReceive('removeInlineKeyboard');

        $this->processor->process($dto);
    }

    /**
     * Неизвестный action — ничего не делает, логирует warning.
     */
    public function test_unknown_action_logs_warning_and_does_nothing(): void
    {
        Log::spy();

        $dto = $this->makeCallbackDTO('unknown:payload');

        $this->telegram->shouldNotReceive('answerCallbackQuery');
        $this->trello->shouldNotReceive('deleteCard');

        $this->processor->process($dto);

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Невалидный формат callback_data — ничего не делает, логирует warning.
     */
    public function test_invalid_callback_data_logs_warning(): void
    {
        Log::spy();

        $dto = $this->makeCallbackDTO('invalid-format');

        $this->telegram->shouldNotReceive('answerCallbackQuery');

        $this->processor->process($dto);

        Log::shouldHaveReceived('warning')->once();
    }

    private function makeCallbackDTO(string $data): TelegramCallbackDTO
    {
        return new TelegramCallbackDTO(
            callbackId: 'cq-1',
            chatId: '100',
            messageId: 42,
            data: $data,
            languageCode: 'ru',
        );
    }
}
