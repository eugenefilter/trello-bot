<?php

declare(strict_types=1);

namespace TelegramBot\CallbackHandlers;

use TelegramBot\DTOs\TelegramCallbackDTO;

interface CallbackActionHandlerInterface
{
    /**
     * @param  TelegramCallbackDTO  $dto  Полный callback_query DTO
     * @param  string  $payload  Часть callback_data после двоеточия (например shortLink для delete)
     */
    public function handle(TelegramCallbackDTO $dto, string $payload): void;
}
