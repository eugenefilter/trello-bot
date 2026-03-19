<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\CallbackHandlers\CallbackActionHandlerInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Services\CallbackQueryProcessor;
use Tests\TestCase;

/**
 * Unit-тест CallbackQueryProcessor.
 *
 * Процессор — чистый роутер. Логика делегируется хэндлерам.
 */
class CallbackQueryProcessorTest extends TestCase
{
    private MockInterface $handler;

    private CallbackQueryProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = Mockery::mock(CallbackActionHandlerInterface::class);

        $this->processor = new CallbackQueryProcessor(
            handlers: ['delete' => $this->handler],
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Известный action — делегирует нужному хэндлеру с payload.
     */
    public function test_routes_to_registered_handler(): void
    {
        $dto = $this->makeCallbackDTO('delete:AbCd1234');

        $this->handler
            ->shouldReceive('handle')
            ->once()
            ->withArgs(fn (TelegramCallbackDTO $d, string $payload) => $d === $dto && $payload === 'AbCd1234'
            );

        $this->processor->process($dto);
    }

    /**
     * Неизвестный action — хэндлер не вызывается, логируется warning.
     */
    public function test_unknown_action_logs_warning_and_does_nothing(): void
    {
        Log::spy();

        $dto = $this->makeCallbackDTO('unknown:payload');

        $this->handler->shouldNotReceive('handle');

        $this->processor->process($dto);

        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Невалидный формат callback_data — хэндлер не вызывается, логируется warning.
     */
    public function test_invalid_callback_data_logs_warning(): void
    {
        Log::spy();

        $dto = $this->makeCallbackDTO('invalid-format');

        $this->handler->shouldNotReceive('handle');

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
