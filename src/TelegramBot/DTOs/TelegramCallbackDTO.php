<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Распарсенный callback_query из Telegram update.
 *
 * Создаётся в TelegramUpdateParser::parseCallback().
 */
class TelegramCallbackDTO
{
    public function __construct(
        public readonly string $callbackId,
        public readonly string $chatId,
        public readonly int $messageId,
        public readonly string $data,
        public readonly ?string $languageCode,
    ) {}
}
