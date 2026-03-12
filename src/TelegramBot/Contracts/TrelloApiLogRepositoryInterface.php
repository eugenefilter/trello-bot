<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

interface TrelloApiLogRepositoryInterface
{
    public function log(
        string $method,
        string $endpoint,
        int $httpStatus,
        ?string $responseBody,
        int $durationMs,
    ): void;
}
