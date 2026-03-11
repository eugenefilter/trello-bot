<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TelegramMessageDTO;

interface UpdateParserInterface
{
    public function parse(array $update): ?TelegramMessageDTO;
}
