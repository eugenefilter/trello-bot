<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\AppSetting;
use App\Models\TrelloApiLog;
use TelegramBot\Contracts\TrelloApiLogRepositoryInterface;

class EloquentTrelloApiLogRepository implements TrelloApiLogRepositoryInterface
{
    public function log(
        string $method,
        string $endpoint,
        int $httpStatus,
        ?string $responseBody,
        int $durationMs,
    ): void {
        if (! AppSetting::getBool('trello_api_logging')) {
            return;
        }

        TrelloApiLog::query()->create([
            'method' => $method,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'response_body' => $responseBody !== null ? mb_substr($responseBody, 0, 2000) : null,
            'duration_ms' => $durationMs,
        ]);
    }
}
