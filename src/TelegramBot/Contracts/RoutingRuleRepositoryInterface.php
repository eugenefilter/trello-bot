<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\RoutingRuleData;

/**
 * Источник правил маршрутизации для RoutingEngine.
 *
 * Интерфейс намеренно возвращает plain-объекты RoutingRuleData, а не Eloquent-модели,
 * чтобы RoutingEngine не зависел от ORM и был тестируемым без БД.
 */
interface RoutingRuleRepositoryInterface
{
    /**
     * Возвращает все активные правила, отсортированные по priority DESC.
     *
     * @return RoutingRuleData[]
     */
    public function getActiveRules(): array;
}
