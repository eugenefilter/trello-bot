<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TrelloConnection;
use App\Services\TrelloNotificationProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTrelloWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        private readonly int $connectionId,
        private readonly string $payload,
    ) {}

    public function handle(TrelloNotificationProcessor $processor): void
    {
        $connection = TrelloConnection::find($this->connectionId);

        if (! $connection || ! $connection->is_active) {
            return;
        }

        $data = json_decode($this->payload, true);

        if (! is_array($data)) {
            return;
        }

        $processor->process($connection, $data);
    }
}
