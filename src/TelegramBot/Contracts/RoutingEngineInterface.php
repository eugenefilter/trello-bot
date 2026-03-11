<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;

/**
 * Определяет правило маршрутизации для входящего сообщения.
 *
 * Подбирает routing rule по комбинации chat_id, command, has_photo и т.д.
 * Возвращает null если ни одно активное правило не подошло.
 */
interface RoutingEngineInterface
{
    /**
     * @return RoutingResultDTO|null null если подходящее правило не найдено
     */
    public function resolve(TelegramMessageDTO $message): ?RoutingResultDTO;
}
