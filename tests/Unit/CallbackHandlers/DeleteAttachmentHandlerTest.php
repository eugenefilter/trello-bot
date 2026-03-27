<?php

declare(strict_types=1);

namespace Tests\Unit\CallbackHandlers;

use Mockery;
use Mockery\MockInterface;
use TelegramBot\CallbackHandlers\DeleteAttachmentHandler;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use Tests\TestCase;

/**
 * Unit-тест DeleteAttachmentHandler.
 *
 * Payload формата "{cardId}/{attachmentId}" парсится и передаётся в deleteAttachment.
 *   - успех → answerCallbackQuery + deleteMessage + sendMessage
 *   - ошибка Trello → answerCallbackQuery с ошибкой + sendMessage, без deleteMessage
 */
class DeleteAttachmentHandlerTest extends TestCase
{
    private MockInterface $telegram;

    private MockInterface $trello;

    private DeleteAttachmentHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->telegram = Mockery::mock(TelegramAdapterInterface::class);
        $this->trello = Mockery::mock(TrelloAdapterInterface::class);

        $this->handler = new DeleteAttachmentHandler(
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
     * Успешное удаление: парсит payload, вызывает deleteAttachment, удаляет сообщение.
     */
    public function test_handle_deletes_attachment_and_confirms(): void
    {
        $dto = $this->makeCallbackDTO('uk');

        $this->trello
            ->shouldReceive('deleteAttachment')
            ->once()
            ->with('card-id-abc', 'att-id-xyz');

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $id === 'cq-1' && str_contains($text, trans('bot.attachment_deleted', [], 'uk')));

        $this->telegram
            ->shouldReceive('deleteMessage')
            ->once()
            ->with('100', 42);

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $chatId === '100' && str_contains($text, trans('bot.attachment_deleted', [], 'uk')));

        $this->handler->handle($dto, 'card-id-abc/att-id-xyz');
    }

    /**
     * Ошибка Trello: подтверждает callback с текстом ошибки, без removeInlineKeyboard.
     */
    public function test_handle_answers_with_error_when_trello_throws(): void
    {
        $dto = $this->makeCallbackDTO('en');

        $this->trello
            ->shouldReceive('deleteAttachment')
            ->once()
            ->andThrow(new \RuntimeException('Trello error'));

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $id === 'cq-1' && str_contains($text, trans('bot.attachment_delete_failed', [], 'en')));

        $this->telegram->shouldNotReceive('deleteMessage');

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $chatId === '100' && str_contains($text, trans('bot.attachment_delete_failed', [], 'en')));

        $this->handler->handle($dto, 'card-id-abc/att-id-xyz');
    }

    /**
     * Неверный формат payload (без слеша) → callback отклоняется, ничего не удаляется.
     */
    public function test_handle_ignores_invalid_payload_format(): void
    {
        $dto = $this->makeCallbackDTO('en');

        $this->trello->shouldNotReceive('deleteAttachment');
        $this->telegram->shouldNotReceive('deleteMessage');
        $this->telegram->shouldNotReceive('sendMessage');

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->with('cq-1', '');

        $this->handler->handle($dto, 'invalid-payload-without-slash');
    }

    /**
     * Неизвестный language_code → fallback на английский.
     */
    public function test_handle_uses_english_for_unknown_language_code(): void
    {
        $dto = $this->makeCallbackDTO('xx');

        $this->trello->shouldReceive('deleteAttachment')->once()->with('card-id-abc', 'att-id-xyz');

        $this->telegram
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(fn (string $id, string $text) => $text === trans('bot.attachment_deleted', [], 'en'));

        $this->telegram->shouldReceive('deleteMessage')->once();

        $this->telegram
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $chatId, string $text) => $text === trans('bot.attachment_deleted', [], 'en'));

        $this->handler->handle($dto, 'card-id-abc/att-id-xyz');
    }

    private function makeCallbackDTO(string $languageCode): TelegramCallbackDTO
    {
        return new TelegramCallbackDTO(
            callbackId: 'cq-1',
            chatId: '100',
            messageId: 42,
            data: 'delete_attachment:card-id-abc/att-id-xyz',
            languageCode: $languageCode,
        );
    }
}
