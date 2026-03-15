<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Разобранное входящее сообщение из Telegram update.
 *
 * Иммутабельный объект — создаётся один раз в TelegramUpdateParser
 * и передаётся без изменений через все слои (Routing → Job → Service).
 */
class TelegramMessageDTO
{
    /**
     * @param  string  $messageType  Тип сообщения: text | photo | text_photo | command
     * @param  array  $photos  Массив file_id фотографий (берётся наибольший размер)
     * @param  array  $documents  Массив file_id документов
     * @param  string  $chatType  Тип чата: private | group | supergroup | channel
     * @param  string|null  $command  Команда бота: /start | /bug | /task | null если не команда
     * @param  string|null  $mediaGroupId  ID группы медиафайлов (если сообщение часть media group)
     * @param  ReplyMessageDTO|null  $replyToMessage  Данные цитируемого сообщения (если есть reply_to_message)
     */
    public function __construct(
        public string $messageType,
        public ?string $text,
        public ?string $caption,
        public array $photos,
        public array $documents,
        public int $userId,
        public string $chatId,
        public string $chatType,
        public ?string $command,
        public ?string $username,
        public ?string $firstName,
        public \DateTimeImmutable $sentAt,
        public ?string $mediaGroupId = null,
        public ?ReplyMessageDTO $replyToMessage = null,
    ) {}

    /**
     * Содержит ли сообщение текст (text или caption у медиа).
     */
    public function hasText(): bool
    {
        return $this->text !== null || $this->caption !== null;
    }

    /**
     * Содержит ли сообщение фото или документ.
     */
    public function hasMedia(): bool
    {
        return count($this->photos) > 0 || count($this->documents) > 0;
    }

    /**
     * Является ли сообщение командой бота (начинается с /).
     */
    public function isCommand(): bool
    {
        return $this->command !== null;
    }
}
