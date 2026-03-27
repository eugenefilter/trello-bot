<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\AttachmentResult;
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
     *
     * @return AttachmentResult|null результат с id и url вложения, null если id отсутствует в ответе
     */
    public function attachFile(string $cardId, string $filePath, string $mimeType): ?AttachmentResult;

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

    /**
     * Обновляет название и описание карточки.
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloConnectionException при других ошибках
     */
    public function updateCard(string $cardId, string $name, string $description): void;

    /**
     * Удаляет карточку по shortLink.
     *
     * 404 игнорируется — карточка уже удалена.
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloConnectionException при других ошибках
     */
    public function deleteCard(string $shortLink): void;

    /**
     * Добавляет комментарий к карточке.
     *
     * @return string|null Trello action ID созданного комментария, null если id отсутствует в ответе
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloConnectionException при других ошибках
     */
    public function addComment(string $cardId, string $text): ?string;

    /**
     * Удаляет комментарий по Trello action ID.
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloConnectionException при других ошибках
     */
    public function deleteComment(string $actionId): void;

    /**
     * Удаляет вложение карточки.
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloConnectionException при других ошибках
     */
    public function deleteAttachment(string $cardId, string $attachmentId): void;
}
