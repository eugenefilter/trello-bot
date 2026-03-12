<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\DTOs\DownloadedFile;
use TelegramBot\Exceptions\TelegramFileException;

/**
 * Скачивает файл из Telegram и сохраняет локально.
 *
 * Порядок:
 *   1. Получает метаданные файла через TelegramAdapter::getFile (file_path)
 *   2. Скачивает файл через TelegramAdapter::downloadFile → возвращает local_path
 *   3. Определяет MIME-тип скачанного файла
 *   4. Обновляет запись telegram_files.local_path через репозиторий
 *   5. Возвращает DownloadedFile для передачи в TrelloAdapter::attachFile
 *
 * @throws TelegramFileException если file_id не найден
 */
class TelegramFileDownloader
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TelegramFileRepositoryInterface $fileRepository,
    ) {}

    public function download(string $fileId, int $telegramMessageId): DownloadedFile
    {
        $fileInfo = $this->telegram->getFile($fileId);

        $localPath = $this->telegram->downloadFile($fileInfo->filePath);

        $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';

        $this->fileRepository->updateLocalPath($fileId, $telegramMessageId, $localPath);

        return new DownloadedFile(
            localPath: $localPath,
            mimeType: $mimeType,
        );
    }
}
