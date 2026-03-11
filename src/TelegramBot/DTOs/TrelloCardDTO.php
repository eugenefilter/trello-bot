<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Данные для создания карточки в Trello.
 *
 * Формируется в TrelloCardCreator на основе TelegramMessageDTO
 * и найденного RoutingResultDTO.
 */
readonly class TrelloCardDTO
{
    /**
     * @param  string  $listId  ID списка Trello, в который создаётся карточка
     * @param  string  $name  Заголовок карточки (из шаблона routing rule)
     * @param  string  $description  Описание карточки (из шаблона routing rule)
     * @param  string[]  $memberIds  Trello member ID для назначения на карточку
     * @param  string[]  $labelIds  Trello label ID для добавления на карточку
     */
    public function __construct(
        public string $listId,
        public string $name,
        public string $description,
        public array $memberIds = [],
        public array $labelIds = [],
    ) {}
}
