<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TelegramFile;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;

class EloquentTelegramFileRepository implements TelegramFileRepositoryInterface
{
    public function createForMessage(int $messageId, array $photoSize, string $fileType): void
    {
        TelegramFile::query()->create([
            'telegram_message_id' => $messageId,
            'file_id' => $photoSize['file_id'],
            'file_unique_id' => $photoSize['file_unique_id'],
            'file_type' => $fileType,
            'size' => $photoSize['file_size'] ?? null,
            'local_path' => null,
            'file_path' => null,
            'mime_type' => null,
        ]);
    }

    public function updateLocalPath(string $fileId, int $messageId, string $localPath): void
    {
        TelegramFile::query()
            ->where('file_id', $fileId)
            ->where('telegram_message_id', $messageId)
            ->update(['local_path' => $localPath]);
    }
}
