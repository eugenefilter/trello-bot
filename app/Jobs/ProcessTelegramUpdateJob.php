<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Services\TelegramUpdateProcessor;
use Throwable;

/**
 * Асинхронно обрабатывает один Telegram update.
 * Вся бизнес-логика вынесена в TelegramUpdateProcessor.
 */
class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    /**
     * Максимальное количество попыток при ошибках Trello.
     */
    public int $tries = 3;

    public function __construct(
        private readonly int $telegramMessageId,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(TelegramUpdateProcessor $processor): void
    {
        $processor->process($this->telegramMessageId);
    }

    public function failed(Throwable $exception): void
    {
        app(TelegramMessageRepositoryInterface::class)->markFailed(
            $this->telegramMessageId,
            $exception->getMessage(),
        );
    }
}
