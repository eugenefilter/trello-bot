<?php

declare(strict_types=1);

namespace TelegramBot\Routing;

use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\RoutingRuleData;
use TelegramBot\DTOs\TelegramMessageDTO;

/**
 * Определяет правило маршрутизации для входящего сообщения.
 *
 * Перебирает активные правила (уже отсортированные по priority DESC репозиторием)
 * и возвращает первое подходящее. Порядок проверки условий внутри правила:
 *   1. telegram_chat_id   — null = любой чат
 *   2. chat_type          — null = любой тип
 *   3. command            — null = любая команда
 *   4. has_photo          — null = не важно
 *
 * Если ни одно правило не подошло — возвращает null.
 */
class RoutingEngine implements RoutingEngineInterface
{
    public function __construct(
        private readonly RoutingRuleRepositoryInterface $repository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(TelegramMessageDTO $message): ?RoutingResultDTO
    {
        foreach ($this->repository->getActiveRules() as $rule) {
            if ($this->matches($rule, $message)) {
                return new RoutingResultDTO(
                    listId: $rule->trelloListId,
                    memberIds: $rule->memberIds,
                    labelIds: $rule->labelIds,
                    cardTitleTemplate: $rule->cardTitleTemplate,
                    cardDescriptionTemplate: $rule->cardDescriptionTemplate,
                );
            }
        }

        return null;
    }

    /**
     * Проверяет, совпадает ли правило с входящим сообщением.
     * Условие null означает «не важно» и всегда проходит проверку.
     */
    private function matches(RoutingRuleData $rule, TelegramMessageDTO $message): bool
    {
        // Совпадение по chat_id
        if ($rule->chatId !== null && (string) $rule->chatId !== $message->chatId) {
            return false;
        }

        // Совпадение по типу чата
        if ($rule->chatType !== null && $rule->chatType !== $message->chatType) {
            return false;
        }

        // Совпадение по команде
        if ($rule->command !== null && $rule->command !== $message->command) {
            return false;
        }

        // Совпадение по наличию фото
        if ($rule->hasPhoto !== null && $rule->hasPhoto !== $message->hasMedia()) {
            return false;
        }

        return true;
    }
}
