<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Данные из reply_to_message — сообщения, на которое ответил пользователь.
 */
class ReplyMessageDTO
{
    /**
     * @param  array  $photos  Массив file_id фотографий (наибольшее разрешение)
     * @param  array  $documents  Массив file_id документов
     */
    public function __construct(
        public readonly ?string $text,
        public readonly ?string $caption,
        public readonly array $photos,
        public readonly array $documents = [],
    ) {}

    public function getText(): string
    {
        return $this->text ?? $this->caption ?? '';
    }
}
