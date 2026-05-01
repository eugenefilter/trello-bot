<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\RoutingRule;
use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\DTOs\RoutingRuleData;

/**
 * Eloquent-реализация репозитория правил маршрутизации.
 *
 * Единственное место в пакете, которое знает об Eloquent-модели RoutingRule.
 * Загружает связанный TrelloList, чтобы получить реальный trello_list_id из Trello API.
 */
class EloquentRoutingRuleRepository implements RoutingRuleRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function getActiveRules(): array
    {
        return RoutingRule::query()
            ->where('is_active', true)
            ->with('targetList')                // eager load — нужен trello_list_id из связи
            ->orderByDesc('priority')
            ->get()
            ->map(fn (RoutingRule $rule) => new RoutingRuleData(
                id: $rule->id,
                chatId: $rule->telegram_chat_id,
                chatType: $rule->chat_type,
                command: $rule->command,
                hasPhoto: $rule->has_photo,
                isForwarded: $rule->is_forwarded,
                trelloListId: $rule->targetList->trello_list_id,
                listName: $rule->targetList->name,
                labelIds: $rule->label_ids ?? [],
                memberIds: $rule->member_ids ?? [],
                cardTitleTemplate: $rule->card_title_template,
                cardDescriptionTemplate: $rule->card_description_template,
                priority: $rule->priority,
            ))
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function getRuleById(int $id): ?RoutingRuleData
    {
        /** @var RoutingRule|null $rule */
        $rule = RoutingRule::query()
            ->where('is_active', true)
            ->with('targetList')
            ->find($id);

        if ($rule === null) {
            return null;
        }

        return new RoutingRuleData(
            id: $rule->id,
            chatId: $rule->telegram_chat_id,
            chatType: $rule->chat_type,
            command: $rule->command,
            hasPhoto: $rule->has_photo,
            isForwarded: $rule->is_forwarded,
            trelloListId: $rule->targetList->trello_list_id,
            listName: $rule->targetList->name,
            labelIds: $rule->label_ids ?? [],
            memberIds: $rule->member_ids ?? [],
            cardTitleTemplate: $rule->card_title_template,
            cardDescriptionTemplate: $rule->card_description_template,
            priority: $rule->priority,
        );
    }
}
