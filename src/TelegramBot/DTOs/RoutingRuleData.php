<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Данные одного правила маршрутизации — plain объект без Eloquent.
 *
 * Возвращается из RoutingRuleRepositoryInterface и передаётся в RoutingEngine.
 * Содержит уже разрешённый trelloListId (из связанной таблицы trello_lists),
 * чтобы RoutingEngine не зависел от ORM-отношений.
 */
class RoutingRuleData
{
    /**
     * @param  int  $id  Первичный ключ записи routing_rules в БД
     * @param  int|null  $chatId  telegram_chat_id из routing_rules (null = любой чат)
     * @param  string|null  $chatType  private | group | supergroup | null = любой
     * @param  string|null  $command  /bug | /task | null = любая команда
     * @param  bool|null  $hasPhoto  true = только с фото, null = не важно
     * @param  bool|null  $isForwarded  true = только пересланные, false = только прямые, null = не важно
     * @param  string  $trelloListId  trello_list_id из таблицы trello_lists (реальный ID в Trello)
     * @param  string  $listName  Название списка Trello (для ответа пользователю)
     * @param  string[]  $labelIds  Trello label IDs из поля label_ids (JSON)
     * @param  string[]  $memberIds  Trello member IDs из поля member_ids (JSON)
     * @param  int  $priority  чем выше — тем приоритетнее при нескольких совпадениях
     */
    public function __construct(
        public int $id,
        public ?int $chatId,
        public ?string $chatType,
        public ?string $command,
        public ?bool $hasPhoto,
        public ?bool $isForwarded,
        public string $trelloListId,
        public string $listName,
        public array $labelIds,
        public array $memberIds,
        public string $cardTitleTemplate,
        public string $cardDescriptionTemplate,
        public int $priority,
    ) {}
}
