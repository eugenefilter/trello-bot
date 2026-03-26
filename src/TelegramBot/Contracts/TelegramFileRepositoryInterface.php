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
     * Создаёт запись о файле при первом получении сообщения.
     * Вызывается из TelegramMessageRepository при сохранении входящего update.
     *
     * @param  array  $photoSize  Один элемент из массива photo[] Telegram (наибольший)
     */
    public function createForMessage(int $messageId, array $photoSize, string $fileType): void;

    /**
     * Сохраняет локальный путь скачанного файла.
     *
     * @param  string  $fileId  Telegram file_id (ключ поиска записи)
     * @param  int  $messageId  ID записи telegram_messages (для уточнения)
     * @param  string  $localPath  Абсолютный путь файла на сервере
     */
    public function updateLocalPath(string $fileId, int $messageId, string $localPath): void;

    /**
     * Возвращает все file_id файлов, сохранённых для данного сообщения.
     * Используется при обработке редактирований для определения новых файлов.
     *
     * @return string[]
     */
    public function getFileIdsByMessageId(int $telegramMessageId): array;
}
