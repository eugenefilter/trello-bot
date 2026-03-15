<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use TelegramBot\DTOs\TelegramMessageDTO;

/**
 * Рендерит шаблон карточки, подставляя значения из TelegramMessageDTO.
 *
 * Поддерживаемые переменные:
 *   {{first_name}}   — имя пользователя
 *   {{username}}     — никнейм пользователя
 *   {{user_id}}      — числовой ID пользователя
 *   {{date}}         — дата отправки (дд.мм.гггг)
 *   {{time}}         — время отправки (чч:мм)
 *   {{text}}         — полный текст сообщения (или caption)
 *   {{text_preview}} — первые 80 символов текста
 *   {{chat_type}}    — тип чата (private | group | supergroup | channel)
 *
 * Неизвестные переменные остаются без изменений.
 */
class CardTemplateRenderer
{
    /**
     * Стандартный шаблон описания карточки (фаза 5.2).
     * Используется как значение по умолчанию для cardDescriptionTemplate
     * при создании routing rules без кастомного шаблона.
     */
    public const DEFAULT_DESCRIPTION = <<<'TPL'
Источник: Telegram
Чат: {{chat_type}}
Пользователь: {{first_name}} (@{{username}})
Telegram user id: {{user_id}}
Дата: {{date}} {{time}}

Текст:
{{text}}

Цитируемое сообщение:
{{reply_text}}
TPL;

    public function render(string $template, TelegramMessageDTO $message): string
    {
        $raw = $message->text ?? $message->caption ?? '';
        $text = ($message->command !== null && str_starts_with($raw, $message->command))
            ? ltrim(mb_substr($raw, mb_strlen($message->command)))
            : $raw;

        $vars = [
            '{{first_name}}' => $message->firstName ?? '',
            '{{username}}' => $message->username ?? '',
            '{{user_id}}' => (string) $message->userId,
            '{{date}}' => $message->sentAt->format('d.m.Y'),
            '{{time}}' => $message->sentAt->format('H:i'),
            '{{text_preview}}' => mb_substr($text, 0, 80),
            '{{text}}' => $text,
            '{{chat_type}}' => $message->chatType,
            '{{reply_text}}' => $message->replyToMessage?->getText() ?? '',
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }
}
