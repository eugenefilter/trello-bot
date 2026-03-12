<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessTelegramUpdateJob;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Services\TelegramUpdateProcessor;
use Tests\TestCase;

/**
 * Тест ProcessTelegramUpdateJob.
 *
 * Job — тонкая обёртка над TelegramUpdateProcessor.
 * Проверяем только что Job делегирует вызов процессору с правильным ID.
 */
class ProcessTelegramUpdateJobTest extends TestCase
{
    /**
     * Job вызывает processor->process() с ID из конструктора.
     */
    public function test_delegates_to_processor(): void
    {
        /** @var MockInterface $processor */
        $processor = Mockery::mock(TelegramUpdateProcessor::class);
        $processor->shouldReceive('process')->once()->with(42);

        (new ProcessTelegramUpdateJob(42))->handle($processor);
    }
}
