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
     */
    public function __construct(
        public readonly ?string $text,
        public readonly ?string $caption,
        public readonly array $photos,
    ) {}

    public function getText(): string
    {
        return $this->text ?? $this->caption ?? '';
    }
}
