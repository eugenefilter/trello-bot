<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\TrelloCardDTO;

/**
 * Контракт для работы с Trello REST API.
 *
 * Все реализации должны быть взаимозаменяемы (LSP).
 * HTTP-клиент инжектируется через конструктор, чтобы в тестах можно было подменить на mock.
 */
interface TrelloAdapterInterface
{
    /**
     * Создаёт карточку в указанном списке.
     */
    public function createCard(TrelloCardDTO $dto): CreatedCardResult;

    /**
     * Прикрепляет файл к карточке по локальному пути.
     */
    public function attachFile(string $cardId, string $filePath, string $mimeType): void;

    /**
     * Назначает участников на карточку.
     *
     * @param  string[]  $memberIds  Trello member ID
     */
    public function addMembersToCard(string $cardId, array $memberIds): void;

    /**
     * Добавляет метки на карточку.
     *
     * @param  string[]  $labelIds  Trello label ID
     */
    public function addLabelsToCard(string $cardId, array $labelIds): void;

    /**
     * Возвращает все списки доски (для синхронизации справочника trello_lists).
     */
    public function getBoardLists(string $boardId): array;

    /**
     * Возвращает все метки доски (для синхронизации справочника trello_labels).
     */
    public function getBoardLabels(string $boardId): array;

    /**
     * Возвращает всех участников доски (для синхронизации справочника trello_members).
     */
    public function getBoardMembers(string $boardId): array;
}
