<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Результат скачивания файла из Telegram на локальный сервер.
 *
 * Передаётся в TrelloAdapter::attachFile для загрузки файла в карточку.
 * После успешной загрузки в Trello файл удаляется с сервера.
 */
readonly class DownloadedFile
{
    public function __construct(
        public string $localPath,
        public string $mimeType,
    ) {}
}
