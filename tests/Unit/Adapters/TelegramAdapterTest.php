<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\TelegramAdapter;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Message;
use Tests\TestCase;

/**
 * Unit-тест TelegramAdapter.
 *
 * Telegram\Bot\Api мокируется через Mockery — реальных запросов к Telegram нет.
 * Log::spy() используется для проверки, что ошибки логируются без выброса исключения.
 */
class TelegramAdapterTest extends TestCase
{
    private MockInterface $bot;

    private TelegramAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bot = Mockery::mock(Api::class);
        $this->adapter = new TelegramAdapter(
            telegram: $this->bot,
            botToken: 'test-bot-token',
            storageDir: sys_get_temp_dir(),
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * sendMessage отправляет POST /sendMessage с chat_id и text.
     */
    public function test_send_message_calls_sdk_with_chat_id_and_text(): void
    {
        $this->bot
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $params) {
                return $params['chat_id'] === '123456'
                    && $params['text'] === 'Привет!';
            });

        $this->adapter->sendMessage('123456', 'Привет!');
    }

    /**
     * sendMessage поддерживает дополнительные параметры (parse_mode, reply_to и т.д.).
     */
    public function test_send_message_passes_options_to_sdk(): void
    {
        $this->bot
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $params) {
                return $params['parse_mode'] === 'Markdown'
                    && $params['chat_id'] === '123456';
            });

        $this->adapter->sendMessage('123456', 'Текст', ['parse_mode' => 'Markdown']);
    }

    /**
     * При ошибке Telegram SDK логируется предупреждение, исключение не пробрасывается.
     */
    public function test_send_message_logs_warning_on_telegram_error(): void
    {
        Log::spy();

        $this->bot
            ->shouldReceive('sendMessage')
            ->andThrow(new \RuntimeException('Bad Request: chat not found'));

        // Не должно бросить исключение
        $this->adapter->sendMessage('123456', 'Текст');

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * sendMessage возвращает message_id отправленного сообщения.
     */
    public function test_send_message_returns_message_id(): void
    {
        $message = Mockery::mock(Message::class);
        $message->shouldReceive('offsetGet')->with('message_id')->andReturn(9876);

        $this->bot
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn($message);

        $result = $this->adapter->sendMessage('123456', 'Текст');

        $this->assertSame(9876, $result);
    }

    /**
     * sendMessage возвращает null при ошибке Telegram SDK.
     */
    public function test_send_message_returns_null_on_error(): void
    {
        Log::spy();

        $this->bot
            ->shouldReceive('sendMessage')
            ->andThrow(new \RuntimeException('Bad Request'));

        $result = $this->adapter->sendMessage('123456', 'Текст');

        $this->assertNull($result);
    }

    /**
     * sendMessage передаёт reply_markup когда он указан в options.
     */
    public function test_send_message_passes_reply_markup_via_options(): void
    {
        $keyboard = ['inline_keyboard' => [[['text' => '🗑 Удалить', 'callback_data' => 'delete:AbCd1234']]]];

        $this->bot
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function (array $params) use ($keyboard) {
                return $params['chat_id'] === '100'
                    && $params['text'] === 'Текст'
                    && $params['reply_markup'] === json_encode($keyboard);
            });

        $this->adapter->sendMessage('100', 'Текст', ['reply_markup' => json_encode($keyboard)]);
    }

    /**
     * answerCallbackQuery вызывает SDK с callbackQueryId и текстом.
     */
    public function test_answer_callback_query_calls_sdk(): void
    {
        $this->bot
            ->shouldReceive('answerCallbackQuery')
            ->once()
            ->withArgs(function (array $params) {
                return $params['callback_query_id'] === 'cq-123'
                    && $params['text'] === '✅ Карточка удалена';
            });

        $this->adapter->answerCallbackQuery('cq-123', '✅ Карточка удалена');
    }

    /**
     * removeInlineKeyboard вызывает editMessageReplyMarkup с пустой клавиатурой.
     */
    public function test_remove_inline_keyboard_edits_message_markup(): void
    {
        $this->bot
            ->shouldReceive('editMessageReplyMarkup')
            ->once()
            ->withArgs(function (array $params) {
                return $params['chat_id'] === '100'
                    && $params['message_id'] === 42
                    && $params['reply_markup'] === json_encode(['inline_keyboard' => []]);
            });

        $this->adapter->removeInlineKeyboard('100', 42);
    }
}
