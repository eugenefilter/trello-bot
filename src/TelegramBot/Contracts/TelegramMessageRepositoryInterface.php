<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

/**
 * Хранение входящих Telegram update в постоянном хранилище.
 */
interface TelegramMessageRepositoryInterface
{
    /**
     * Сохраняет update если он ещё не существует (идемпотентно по update_id).
     *
     * @param  array  $payload  Полный сырой payload от Telegram
     * @return array{id: int, created: bool}
     */
    public function firstOrCreate(array $payload): array;

    /**
     * Возвращает сырой payload_json по ID записи.
     *
     * @throws \RuntimeException если запись не найдена
     */
    public function getPayload(int $id): array;

    /**
     * Проставляет время успешной обработки сообщения (processing_status = success).
     */
    public function markProcessed(int $id): void;

    /**
     * Помечает сообщение как пропущенное (не нашли парсер или правило маршрутизации).
     */
    public function markSkipped(int $id, string $reason): void;

    /**
     * Помечает сообщение как окончательно упавшее (все retry исчерпаны).
     */
    public function markFailed(int $id, string $reason): void;

    /**
     * Возвращает trello_card_id для группы медиафайлов, если карточка уже создана.
     * Используется для прикрепления файлов из "догоняющих" update той же группы.
     *
     * @return string|null trello_card_id или null если карточки ещё нет
     */
    public function findCardIdByMediaGroup(string $mediaGroupId): ?string;

    /**
     * Возвращает части медиагруппы со статусом skipped, кроме главного сообщения.
     * Используется для retroactive-прикрепления файлов к только что созданной карточке.
     *
     * @return array<array{id: int, file_ids: string[]}>
     */
    public function findSkippedGroupParts(string $mediaGroupId, int $excludeMessageId): array;

    /**
     * Ищет успешно созданную карточку Trello по Telegram chat_id и message_id.
     * Используется при обработке edited_message для нахождения исходной карточки.
     *
     * @return array{telegram_message_id: int, card_id: string, card_url: string}|null
     */
    public function findOriginalCardByMessage(string $chatId, int $messageId): ?array;
}
