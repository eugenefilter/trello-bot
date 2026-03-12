<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Результат маршрутизации входящего сообщения.
 *
 * Формируется в RoutingEngine по найденному routing rule
 * и передаётся в TrelloCardCreator для создания карточки.
 */
class RoutingResultDTO
{
    /**
     * @param  string  $listId  ID списка Trello, в который создаётся карточка
     * @param  string[]  $memberIds  Trello member ID для назначения
     * @param  string[]  $labelIds  Trello label ID для добавления
     * @param  string  $cardTitleTemplate  Шаблон заголовка: "{{first_name}}: {{text_preview}}"
     * @param  string  $cardDescriptionTemplate  Шаблон описания с переменными
     */
    public function __construct(
        public string $listId,
        public array $memberIds,
        public array $labelIds,
        public string $cardTitleTemplate,
        public string $cardDescriptionTemplate,
    ) {}
}
