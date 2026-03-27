<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Результат прикрепления файла к карточке Trello.
 */
class AttachmentResult
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $url,
    ) {}
}
