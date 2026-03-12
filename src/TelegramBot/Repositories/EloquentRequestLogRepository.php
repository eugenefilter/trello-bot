<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TelegramRequestLog;
use TelegramBot\Contracts\RequestLogRepositoryInterface;

class EloquentRequestLogRepository implements RequestLogRepositoryInterface
{
    public function log(array $payload): void
    {
        TelegramRequestLog::query()->create([
            'payload' => $payload,
            'received_at' => now(),
        ]);
    }
}
