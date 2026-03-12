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
}
