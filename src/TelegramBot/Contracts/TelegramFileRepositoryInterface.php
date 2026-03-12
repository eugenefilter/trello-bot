<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

/**
 * Репозиторий для работы с записями telegram_files.
 *
 * Используется TelegramFileDownloader для обновления local_path
 * после скачивания файла с серверов Telegram.
 */
interface TelegramFileRepositoryInterface
{
    /**
     * Сохраняет локальный путь скачанного файла.
     *
     * @param  string  $fileId  Telegram file_id (ключ поиска записи)
     * @param  int  $messageId  ID записи telegram_messages (для уточнения)
     * @param  string  $localPath  Абсолютный путь файла на сервере
     */
    public function updateLocalPath(string $fileId, int $messageId, string $localPath): void;
}
