<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TelegramFileInfo;

interface TelegramAdapterInterface
{
    public function sendMessage(string $chatId, string $text, array $options = []): void;

    public function getFile(string $fileId): TelegramFileInfo;

    public function downloadFile(string $filePath): string;
}
