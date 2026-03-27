<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TelegramFileInfo;

/**
 * Контракт для взаимодействия с Telegram Bot API.
 *
 * Ошибки отправки сообщений не должны прерывать основной поток —
 * реализации логируют их как warning, но не кидают исключения.
 */
interface TelegramAdapterInterface
{
    /**
     * Отправляет текстовое сообщение в чат.
     *
     * @param  array  $options  Дополнительные параметры (parse_mode, reply_to_message_id и т.д.)
     * @return int|null message_id отправленного сообщения или null при ошибке
     */
    public function sendMessage(string $chatId, string $text, array $options = []): ?int;

    /**
     * Получает метаданные файла по file_id (вызов getFile Telegram API).
     */
    public function getFile(string $fileId): TelegramFileInfo;

    /**
     * Скачивает файл по file_path (относительный путь из getFile) и возвращает локальный путь.
     */
    public function downloadFile(string $filePath): string;

    /**
     * Отвечает на callback_query (обязательный ответ Telegram при нажатии кнопки).
     */
    public function answerCallbackQuery(string $callbackId, string $text): void;

    /**
     * Убирает inline-клавиатуру из сообщения (после выполнения действия).
     */
    public function removeInlineKeyboard(string $chatId, int $messageId): void;
}
