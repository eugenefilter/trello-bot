<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Parsers\TelegramUpdateParser;
use TelegramBot\Services\CallbackQueryProcessor;
use TelegramBot\Services\TelegramEditProcessor;
use TelegramBot\Services\TelegramUpdateProcessor;
use Throwable;

/**
 * Асинхронно обрабатывает один Telegram update.
 *
 * Роутит по типу update:
 *   - message       → TelegramUpdateProcessor
 *   - callback_query → CallbackQueryProcessor
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
    public function handle(
        TelegramMessageRepositoryInterface $repository,
        TelegramUpdateProcessor $messageProcessor,
        CallbackQueryProcessor $callbackProcessor,
        TelegramEditProcessor $editProcessor,
        TelegramUpdateParser $parser,
    ): void {
        $payload = $repository->getPayload($this->telegramMessageId);

        if (isset($payload['callback_query'])) {
            $dto = $parser->parseCallback($payload);

            if ($dto !== null) {
                $callbackProcessor->process($dto);
                $repository->markProcessed($this->telegramMessageId);
            }

            return;
        }

        if (isset($payload['edited_message'])) {
            $editProcessor->process($this->telegramMessageId);

            return;
        }

        $messageProcessor->process($this->telegramMessageId);
    }

    public function failed(Throwable $exception): void
    {
        app(TelegramMessageRepositoryInterface::class)->markFailed(
            $this->telegramMessageId,
            $exception->getMessage(),
        );
    }
}
