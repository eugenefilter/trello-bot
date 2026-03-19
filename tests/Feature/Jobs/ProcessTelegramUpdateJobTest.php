<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessTelegramUpdateJob;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Parsers\TelegramUpdateParser;
use TelegramBot\Services\CallbackQueryProcessor;
use TelegramBot\Services\TelegramUpdateProcessor;
use Tests\TestCase;

/**
 * Тест ProcessTelegramUpdateJob.
 *
 * Job — тонкая обёртка, роутит по типу update:
 *   - message → TelegramUpdateProcessor
 *   - callback_query → CallbackQueryProcessor
 */
class ProcessTelegramUpdateJobTest extends TestCase
{
    private MockInterface $repository;

    private MockInterface $messageProcessor;

    private MockInterface $callbackProcessor;

    private MockInterface $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(TelegramMessageRepositoryInterface::class);
        $this->messageProcessor = Mockery::mock(TelegramUpdateProcessor::class);
        $this->callbackProcessor = Mockery::mock(CallbackQueryProcessor::class);
        $this->parser = Mockery::mock(TelegramUpdateParser::class);
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
    }

    /**
     * message payload → делегирует TelegramUpdateProcessor с ID.
     */
    public function test_delegates_message_to_update_processor(): void
    {
        $this->repository->shouldReceive('getPayload')->with(42)->andReturn(['message' => []]);
        $this->messageProcessor->shouldReceive('process')->once()->with(42);
        $this->callbackProcessor->shouldNotReceive('process');

        $this->makeJob(42)->handle(
            $this->repository,
            $this->messageProcessor,
            $this->callbackProcessor,
            $this->parser,
        );
    }

    /**
     * callback_query payload → парсит DTO и делегирует CallbackQueryProcessor.
     */
    public function test_delegates_callback_query_to_callback_processor(): void
    {
        $dto = new TelegramCallbackDTO('cq-1', '100', 42, 'delete:AbCd1234', 'ru');

        $this->repository->shouldReceive('getPayload')->with(99)->andReturn(['callback_query' => []]);
        $this->repository->shouldReceive('markProcessed')->once()->with(99);
        $this->parser->shouldReceive('parseCallback')->once()->andReturn($dto);
        $this->callbackProcessor->shouldReceive('process')->once()->with($dto);
        $this->messageProcessor->shouldNotReceive('process');

        $this->makeJob(99)->handle(
            $this->repository,
            $this->messageProcessor,
            $this->callbackProcessor,
            $this->parser,
        );
    }

    private function makeJob(int $id): ProcessTelegramUpdateJob
    {
        return new ProcessTelegramUpdateJob($id);
    }
}
