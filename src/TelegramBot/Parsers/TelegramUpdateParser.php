<?php

declare(strict_types=1);

namespace TelegramBot\Parsers;

use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\ReplyMessageDTO;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\DTOs\TelegramMessageDTO;

/**
 * Разбирает сырой Telegram update в TelegramMessageDTO.
 *
 * Не делает HTTP-вызовов — только чистая трансформация данных.
 * Порядок определения messageType:
 *   1. есть фото + caption → text_photo
 *   2. есть фото без caption → photo
 *   3. текст начинается с / → command
 *   4. иначе → text
 */
class TelegramUpdateParser implements UpdateParserInterface
{
    public function parse(array $update): ?TelegramMessageDTO
    {
        $message = $update['message'] ?? null;

        // update без message (edited_message, poll, inline_query и т.д.) не обрабатываем
        if ($message === null) {
            return null;
        }

        return $this->parseMessage($message);
    }

    public function parseEdit(array $update): ?TelegramMessageDTO
    {
        $message = $update['edited_message'] ?? null;

        if ($message === null) {
            return null;
        }

        return $this->parseMessage($message);
    }

    private function parseMessage(array $message): TelegramMessageDTO
    {
        $text = $message['text'] ?? null;
        $caption = $message['caption'] ?? null;
        $photos = $this->extractPhotos($message);

        // Команда может быть как в text (entities), так и в caption (caption_entities)
        $command = $this->extractCommand($text, $message['entities'] ?? [])
            ?? $this->extractCommand($caption, $message['caption_entities'] ?? []);

        return new TelegramMessageDTO(
            messageType: $this->resolveMessageType($text, $caption, $photos, $command),
            text: $text,
            caption: $caption,
            photos: $photos,
            documents: $this->extractDocuments($message),
            userId: $message['from']['id'],
            chatId: (string) $message['chat']['id'],
            chatType: $message['chat']['type'],
            command: $command,
            username: $message['from']['username'] ?? null,
            firstName: $message['from']['first_name'] ?? null,
            sentAt: new \DateTimeImmutable('@'.$message['date']),
            mediaGroupId: $message['media_group_id'] ?? null,
            replyToMessage: $this->extractReplyMessage($message),
            languageCode: $message['from']['language_code'] ?? null,
            messageId: $message['message_id'] ?? null,
            replyToMessageId: $message['reply_to_message']['message_id'] ?? null,
        );
    }

    /**
     * Парсит callback_query update в TelegramCallbackDTO.
     *
     * Возвращает null если update не является callback_query.
     */
    public function parseCallback(array $update): ?TelegramCallbackDTO
    {
        $callback = $update['callback_query'] ?? null;

        if ($callback === null) {
            return null;
        }

        return new TelegramCallbackDTO(
            callbackId: $callback['id'],
            chatId: (string) $callback['message']['chat']['id'],
            messageId: $callback['message']['message_id'],
            data: $callback['data'],
            languageCode: $callback['from']['language_code'] ?? null,
        );
    }

    /**
     * Определяет тип сообщения на основе содержимого.
     *
     * @param  string[]  $photos
     */
    private function resolveMessageType(?string $text, ?string $caption, array $photos, ?string $command): string
    {
        if (count($photos) > 0 && $caption !== null) {
            return 'text_photo';
        }

        if (count($photos) > 0) {
            return 'photo';
        }

        if ($command !== null) {
            return 'command';
        }

        return 'text';
    }

    /**
     * Извлекает команду из entities (поддерживает /cmd@botname и @mention /cmd).
     * Fallback: текст начинается с / — разбираем вручную.
     */
    private function extractCommand(?string $text, array $entities): ?string
    {
        if ($text === null) {
            return null;
        }

        // Используем entities — Telegram явно помечает bot_command
        foreach ($entities as $entity) {
            if ($entity['type'] === 'bot_command') {
                $raw = mb_substr($text, $entity['offset'], $entity['length']);

                // /bug@botname → /bug
                return explode('@', $raw)[0];
            }
        }

        // Fallback для личных чатов без entities (или старых клиентов)
        if (str_starts_with($text, '/')) {
            return explode(' ', $text)[0];
        }

        return null;
    }

    /**
     * Извлекает file_id фотографий.
     *
     * Telegram присылает массив PhotoSize от меньшего к большему разрешению.
     * Берём file_id последнего элемента (наибольшее качество).
     */
    private function extractPhotos(array $message): array
    {
        $photoSizes = $message['photo'] ?? [];

        if (empty($photoSizes)) {
            return [];
        }

        // Последний элемент — максимальное разрешение
        $largest = end($photoSizes);

        return [$largest['file_id']];
    }

    /**
     * Извлекает данные цитируемого сообщения (reply_to_message).
     */
    private function extractReplyMessage(array $message): ?ReplyMessageDTO
    {
        $reply = $message['reply_to_message'] ?? null;

        if ($reply === null) {
            return null;
        }

        return new ReplyMessageDTO(
            text: $reply['text'] ?? null,
            caption: $reply['caption'] ?? null,
            photos: $this->extractPhotos($reply),
            documents: $this->extractDocuments($reply),
        );
    }

    /**
     * Извлекает file_id документов (файлы, gif, sticker и т.д.).
     */
    private function extractDocuments(array $message): array
    {
        $document = $message['document'] ?? null;

        if ($document === null) {
            return [];
        }

        return [$document['file_id']];
    }
}
