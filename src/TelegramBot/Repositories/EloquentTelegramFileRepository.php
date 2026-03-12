<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TelegramFile;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;

class EloquentTelegramFileRepository implements TelegramFileRepositoryInterface
{
    public function updateLocalPath(string $fileId, int $messageId, string $localPath): void
    {
        TelegramFile::query()->where('file_id', $fileId)
            ->where('telegram_message_id', $messageId)
            ->update(['local_path' => $localPath]);
    }
}
