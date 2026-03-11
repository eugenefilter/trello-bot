<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

/**
 * Контракт для сохранения результатов попыток создания карточек Trello.
 *
 * Отделяет бизнес-логику TrelloCardCreator от деталей хранения (Eloquent).
 * Реализация живёт в app/Repositories/ и использует модель TrelloCardLog.
 */
interface CardLogRepositoryInterface
{
    /**
     * Записывает успешное создание карточки.
     */
    public function logSuccess(
        int    $telegramMessageId,
        string $listId,
        string $cardId,
        string $cardUrl,
    ): void;

    /**
     * Записывает неудачную попытку создания карточки.
     */
    public function logError(
        int    $telegramMessageId,
        string $listId,
        string $errorMessage,
    ): void;
}
