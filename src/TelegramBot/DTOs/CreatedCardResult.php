<?php

declare(strict_types=1);

namespace TelegramBot\DTOs;

/**
 * Результат успешного создания карточки в Trello.
 *
 * Возвращается из TrelloAdapterInterface::createCard()
 * и сохраняется в trello_cards_log.
 */
class CreatedCardResult
{
    /**
     * @param  string  $id  ID созданной карточки в Trello API
     * @param  string  $shortLink  Короткий ID карточки (используется в callback_data кнопки удаления)
     * @param  string  $url  Ссылка на карточку для ответа пользователю
     */
    public function __construct(
        public string $id,
        public string $shortLink,
        public string $url,
    ) {}
}
