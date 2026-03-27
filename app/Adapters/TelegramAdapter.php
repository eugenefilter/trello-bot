<?php

declare(strict_types=1);

namespace App\Adapters;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\DTOs\TelegramFileInfo;
use TelegramBot\Exceptions\TelegramFileException;

/**
 * Реализация TelegramAdapterInterface через Telegram Bot SDK.
 *
 * SDK используется только в app/ слое согласно архитектурному решению
 * (см. DEVELOPMENT_PLAN.md — Стратегия интеграции Telegram Bot SDK).
 *
 * Ошибки sendMessage логируются как warning и не пробрасываются —
 * отправка ответа не должна блокировать основной поток обработки.
 */
class TelegramAdapter implements TelegramAdapterInterface
{
    public function __construct(
        private readonly Api $telegram,
        private readonly string $botToken,
        private readonly string $storageDir,
    ) {}

    /**
     * {@inheritDoc}
     *
     * Ошибки логируются как warning — не критично для основного потока.
     */
    public function sendMessage(string $chatId, string $text, array $options = []): ?int
    {
        try {
            $response = $this->telegram->sendMessage(array_merge([
                'chat_id' => $chatId,
                'text' => $text,
            ], $options));

            $messageId = $response->offsetGet('message_id');

            return $messageId !== null ? (int) $messageId : null;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws TelegramFileException если file_id не найден в Telegram
     */
    public function getFile(string $fileId): TelegramFileInfo
    {
        try {
            $file = $this->telegram->getFile(['file_id' => $fileId]);
        } catch (\Throwable $e) {
            throw new TelegramFileException(
                "Failed to get file info for file_id={$fileId}: {$e->getMessage()}",
                previous: $e,
            );
        }

        return new TelegramFileInfo(
            fileId: $file->fileId,
            filePath: $file->filePath,
            fileSize: $file->fileSize,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function answerCallbackQuery(string $callbackId, string $text): void
    {
        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery failed', [
                'callback_query_id' => $callbackId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function removeInlineKeyboard(string $chatId, int $messageId): void
    {
        try {
            $this->telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode(['inline_keyboard' => []]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram removeInlineKeyboard failed', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMessage(string $chatId, int $messageId): void
    {
        try {
            $this->telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Telegram deleteMessage failed', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     *
     * Скачивает файл по file_path из Telegram и сохраняет в storageDir.
     * Возвращает абсолютный локальный путь.
     *
     * @throws TelegramFileException при ошибке скачивания
     */
    public function downloadFile(string $filePath): string
    {
        if (! is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        $url = "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
        $localPath = $this->storageDir.'/'.basename($filePath);

        $content = @file_get_contents($url);

        if ($content === false) {
            throw new TelegramFileException("Failed to download file: {$filePath}");
        }

        if (file_put_contents($localPath, $content) === false) {
            throw new TelegramFileException("Failed to save file to: {$localPath}");
        }

        return $localPath;
    }
}
