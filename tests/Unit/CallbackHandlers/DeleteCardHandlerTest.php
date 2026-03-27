<?php

declare(strict_types=1);

namespace Tests\Unit\CallbackHandlers;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\CallbackHandlers\DeleteCardHandler;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Tests\TestCase;

/**
 * Unit-тест DeleteCardHandler.
 *
 * Проверяет бизнес-логику обработки действия "delete":
 *   - успешное удаление → answerCallbackQuery + deleteMessage + sendMessage с подтверждением
 *   - ошибка Trello → answerCallbackQuery с текстом ошибки + sendMessage с ошибкой, без deleteMessage
 */
class DeleteCardHandlerTest extends TestCase
{
    private MockInterface $telegram;

    private MockInterface $trello;

    private DeleteCardHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->trello = Mockery::mock(TrelloAdapterInterface::class);

        $this->handler = new DeleteCardHandler(
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
     * Успешное удаление: подтверждает callback, удаляет сообщение, шлёт сообщение в чат.
     */
    public function test_handle_deletes_card_and_confirms(): void
    {
        $dto = $this->makeCallbackDTO('ru');

        $this->trello
            ->shouldReceive('deleteCard')
            ->once()
            ->with('AbCd1234');

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $id === 'cq-1' && str_contains($text, trans('bot.card_deleted', [], 'ru'))
            );

        $this->telegram
            ->shouldReceive('deleteMessage')
            ->once()
            ->with('100', 42);

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $chatId === '100' && str_contains($text, trans('bot.card_deleted', [], 'ru'))
            );

        $this->handler->handle($dto, 'AbCd1234');
    }

    /**
     * Ошибка Trello: подтверждает callback с текстом ошибки, шлёт сообщение в чат, клавиатуру не убирает.
     */
    public function test_handle_answers_with_error_when_trello_throws(): void
    {
        $dto = $this->makeCallbackDTO('en');

        $this->trello
            ->shouldReceive('deleteCard')
            ->once()
            ->andThrow(new \RuntimeException('Trello error'));

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $id === 'cq-1' && str_contains($text, trans('bot.card_delete_failed', [], 'en'))
            );

        $this->telegram->shouldNotReceive('deleteMessage');

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $chatId === '100' && str_contains($text, trans('bot.card_delete_failed', [], 'en'))
            );

        $this->handler->handle($dto, 'AbCd1234');
    }

    /**
     * Неизвестный language_code → fallback на английский.
     */
    public function test_handle_uses_english_for_unknown_language_code(): void
    {
        $dto = $this->makeCallbackDTO('xx');

        $this->trello->shouldReceive('deleteCard')->once()->with('AbCd1234');

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $text === trans('bot.card_deleted', [], 'en')
            );

        $this->telegram->shouldReceive('deleteMessage')->once();

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $chatId === '100' && $text === trans('bot.card_deleted', [], 'en')
            );

        $this->handler->handle($dto, 'AbCd1234');
    }

    private function makeCallbackDTO(string $languageCode): TelegramCallbackDTO
    {
        return new TelegramCallbackDTO(
            callbackId: 'cq-1',
            chatId: '100',
            messageId: 42,
            data: 'delete:AbCd1234',
            languageCode: $languageCode,
        );
    }
}
