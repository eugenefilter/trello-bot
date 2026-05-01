<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Данные об оригинальном отправителе переадресованного сообщения.
 *
 * Заполняется из forward_origin (новый API) или forward_from (старый API).
 *
 * @param string $type Тип источника: user | hidden_user | chat | channel
 * @param string $firstName Имя оригинального отправителя
 * @param string|null $username Никнейм (может отсутствовать у hidden_user)
 * @param int|null $userId Числовой ID (null для hidden_user)
 */
class ForwardOriginDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $firstName,
        public readonly ?string $username = null,
        public readonly ?int $userId = null,
    ) {}
}
