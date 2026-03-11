<?php

declare(strict_types=1);

namespace TelegramBot\Parsers;

use TelegramBot\Contracts\UpdateParserInterface;
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

        $text = $message['text'] ?? null;
        $caption = $message['caption'] ?? null;
        $photos = $this->extractPhotos($message);

        return new TelegramMessageDTO(
            messageType: $this->resolveMessageType($text, $caption, $photos),
            text: $text,
            caption: $caption,
            photos: $photos,
            documents: $this->extractDocuments($message),
            userId: $message['from']['id'],
            chatId: (string) $message['chat']['id'],
            chatType: $message['chat']['type'],
            command: $this->extractCommand($text),
            username: $message['from']['username'] ?? null,
            firstName: $message['from']['first_name'] ?? null,
            sentAt: new \DateTimeImmutable('@'.$message['date']),
        );
    }

    /**
     * Определяет тип сообщения на основе содержимого.
     *
     * @param  string[]  $photos
     */
    private function resolveMessageType(?string $text, ?string $caption, array $photos): string
    {
        if (count($photos) > 0 && $caption !== null) {
            return 'text_photo';
        }

        if (count($photos) > 0) {
            return 'photo';
        }

        if ($text !== null && str_starts_with($text, '/')) {
            return 'command';
        }

        return 'text';
    }

    /**
     * Извлекает команду из текста (первое слово, начинающееся с /).
     * Например "/bug fix login" → "/bug".
     */
    private function extractCommand(?string $text): ?string
    {
        if ($text === null || ! str_starts_with($text, '/')) {
            return null;
        }

        // Берём только первое слово — сама команда без аргументов
        return explode(' ', $text)[0];
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
