<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

interface RequestLogRepositoryInterface
{
    public function log(array $payload): void;
}
