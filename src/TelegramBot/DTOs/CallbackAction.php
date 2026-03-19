<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Распарсенное действие из callback_data кнопки.
 *
 * Формат callback_data: "action:payload" (например "delete:AbCd1234").
 */
class CallbackAction
{
    public function __construct(
        public readonly string $action,
        public readonly string $payload,
    ) {}

    /**
     * Парсит callback_data в CallbackAction.
     *
     * Возвращает null если формат не соответствует "action:payload".
     */
    public static function fromData(string $data): ?self
    {
        $parts = explode(':', $data, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return new self(action: $parts[0], payload: $parts[1]);
    }
}
