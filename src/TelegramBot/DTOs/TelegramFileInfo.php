<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Метаданные файла, возвращаемые Telegram API (метод getFile).
 *
 * file_path — относительный путь для скачивания через Telegram API.
 * Не хранится постоянно — актуален около часа после получения.
 */
readonly class TelegramFileInfo
{
    public function __construct(
        public string $fileId,
        public string $filePath,
        public ?int $fileSize,
    ) {}
}
