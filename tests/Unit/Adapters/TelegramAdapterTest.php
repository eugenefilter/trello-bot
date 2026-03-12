<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\TelegramAdapter;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Telegram\Bot\Api;
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
}
