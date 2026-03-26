<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TelegramMessageDTO;

/**
 * Разбирает сырой Telegram update в TelegramMessageDTO.
 *
 * Реализации не должны делать HTTP-вызовы — только чистая трансформация данных.
 * Это позволяет тестировать парсер изолированно с fixture-массивами.
 */
interface UpdateParserInterface
{
    /**
     * @param  array  $update  Сырой payload от Telegram (поле update целиком)
     * @return TelegramMessageDTO|null null если update не содержит message
     */
    public function parse(array $update): ?TelegramMessageDTO;

    /**
     * Парсит edited_message update в TelegramMessageDTO.
     *
     * @return TelegramMessageDTO|null null если update не содержит edited_message
     */
    public function parseEdit(array $update): ?TelegramMessageDTO;
}
