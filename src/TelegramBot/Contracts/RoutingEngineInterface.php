<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\DTOs\RoutingResultDTO;

interface RoutingEngineInterface
{
    public function resolve(TelegramMessageDTO $message): ?RoutingResultDTO;
}
