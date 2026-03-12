<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use PHPUnit\Framework\TestCase;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Parsers\TelegramUpdateParser;

/**
 * Unit-тест парсера Telegram update.
 *
 * Тесты не используют Laravel — только чистый PHP.
 * Каждый тест передаёт fixture-массив и проверяет поля DTO.
 */
class TelegramUpdateParserTest extends TestCase
{
    private TelegramUpdateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TelegramUpdateParser;
    }

    /**
     * Обычное текстовое сообщение → messageType = 'text'.
     */
    public function test_parses_text_message(): void
    {
        $dto = $this->parser->parse($this->textUpdate('Hello world'));

        $this->assertInstanceOf(TelegramMessageDTO::class, $dto);
        $this->assertSame('text', $dto->messageType);
        $this->assertSame('Hello world', $dto->text);
        $this->assertNull($dto->caption);
        $this->assertNull($dto->command);
    }

    /**
     * Фото без подписи → messageType = 'photo'.
     */
    public function test_parses_photo_without_caption(): void
    {
        $dto = $this->parser->parse($this->photoUpdate(caption: null));

        $this->assertSame('photo', $dto->messageType);
        $this->assertNull($dto->caption);
        $this->assertNotEmpty($dto->photos);
    }

    /**
     * Фото с подписью → messageType = 'text_photo'.
     */
    public function test_parses_photo_with_caption(): void
    {
        $dto = $this->parser->parse($this->photoUpdate(caption: 'Смотри фото'));

        $this->assertSame('text_photo', $dto->messageType);
        $this->assertSame('Смотри фото', $dto->caption);
    }

    /**
     * Сообщение начинается с / → messageType = 'command', command заполнен.
     */
    public function test_parses_command(): void
    {
        $dto = $this->parser->parse($this->textUpdate('/bug fix login'));

        $this->assertSame('command', $dto->messageType);
        $this->assertSame('/bug', $dto->command);
    }

    /**
     * Telegram присылает массив PhotoSize с разными разрешениями.
     * Парсер должен взять file_id последнего элемента (наибольшее разрешение).
     */
    public function test_takes_largest_photo(): void
    {
        $update = $this->photoUpdate();
        // В fixture три размера — ожидаем file_id последнего (наибольшего)
        $dto = $this->parser->parse($update);

        $this->assertSame('file_id_large', $dto->photos[0]);
    }

    /**
     * Тип чата правильно пробрасывается в DTO.
     */
    public function test_detects_chat_type(): void
    {
        foreach (['private', 'group', 'supergroup'] as $type) {
            $dto = $this->parser->parse($this->textUpdate('hi', chatType: $type));
            $this->assertSame($type, $dto->chatType);
        }
    }

    /**
     * update без поля message (например, edited_message, poll и т.д.) → null.
     */
    public function test_returns_null_if_no_message(): void
    {
        $dto = $this->parser->parse(['update_id' => 1]);

        $this->assertNull($dto);
    }

    /**
     * Базовые идентификаторы пользователя и чата корректно извлекаются.
     */
    public function test_extracts_user_and_chat_ids(): void
    {
        $dto = $this->parser->parse($this->textUpdate('hi'));

        $this->assertSame(111111, $dto->userId);
        $this->assertSame('222222', $dto->chatId);
        $this->assertSame('testuser', $dto->username);
        $this->assertSame('Test', $dto->firstName);
    }

    /**
     * Фото с caption "/bug текст" → команда извлекается из caption_entities.
     */
    public function test_extracts_command_from_photo_caption(): void
    {
        $dto = $this->parser->parse($this->photoWithCaptionCommandUpdate('/bug Тест карточки с фото'));

        $this->assertSame('text_photo', $dto->messageType); // фото+caption → text_photo
        $this->assertSame('/bug', $dto->command);           // команда всё равно извлекается
        $this->assertSame('/bug Тест карточки с фото', $dto->caption);
    }

    /**
     * /bug@botname в группе → команда должна быть /bug (без суффикса @botname).
     */
    public function test_strips_botname_suffix_from_command(): void
    {
        $dto = $this->parser->parse($this->commandWithEntityUpdate('/bug@itsell_trello_bot fix login'));

        $this->assertSame('command', $dto->messageType);
        $this->assertSame('/bug', $dto->command);
    }

    /**
     * @mention /command в группе → команда извлекается через entities.
     */
    public function test_parses_command_after_mention(): void
    {
        $text = '@itsell_trello_bot /bug Не работает вход';
        $dto = $this->parser->parse($this->mentionCommandUpdate($text));

        $this->assertSame('command', $dto->messageType);
        $this->assertSame('/bug', $dto->command);
    }

    // --- Fixtures ---

    private function textUpdate(string $text, string $chatType = 'private'): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => 222222, 'type' => $chatType],
                'date' => 1700000000,
                'text' => $text,
            ],
        ];
    }

    /** Фото с caption-командой */
    private function photoWithCaptionCommandUpdate(string $caption): array
    {
        return [
            'update_id' => 5,
            'message' => [
                'message_id' => 14,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => 222222, 'type' => 'private'],
                'date' => 1700000000,
                'caption' => $caption,
                'caption_entities' => [
                    ['type' => 'bot_command', 'offset' => 0, 'length' => 4],
                ],
                'photo' => [
                    ['file_id' => 'file_id_large', 'file_unique_id' => 'u1', 'width' => 800, 'height' => 800],
                ],
            ],
        ];
    }

    /** /bug@botname — entities содержит bot_command с суффиксом */
    private function commandWithEntityUpdate(string $text): array
    {
        return [
            'update_id' => 3,
            'message' => [
                'message_id' => 12,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => -100222222, 'type' => 'supergroup'],
                'date' => 1700000000,
                'text' => $text,
                'entities' => [
                    ['type' => 'bot_command', 'offset' => 0, 'length' => strlen('/bug@itsell_trello_bot')],
                ],
            ],
        ];
    }

    /** @mention /command — entities содержат mention + bot_command */
    private function mentionCommandUpdate(string $text): array
    {
        return [
            'update_id' => 4,
            'message' => [
                'message_id' => 13,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => -100222222, 'type' => 'supergroup'],
                'date' => 1700000000,
                'text' => $text,
                'entities' => [
                    ['type' => 'mention',     'offset' => 0,  'length' => strlen('@itsell_trello_bot')],
                    ['type' => 'bot_command', 'offset' => 19, 'length' => strlen('/bug')],
                ],
            ],
        ];
    }

    private function photoUpdate(?string $caption = null): array
    {
        return [
            'update_id' => 2,
            'message' => [
                'message_id' => 11,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => 222222, 'type' => 'private'],
                'date' => 1700000000,
                'caption' => $caption,
                // Telegram присылает массив от меньшего к большему разрешению
                'photo' => [
                    ['file_id' => 'file_id_small',  'file_unique_id' => 'u1', 'width' => 90,  'height' => 90],
                    ['file_id' => 'file_id_medium', 'file_unique_id' => 'u2', 'width' => 320, 'height' => 320],
                    ['file_id' => 'file_id_large',  'file_unique_id' => 'u3', 'width' => 800, 'height' => 800],
                ],
            ],
        ];
    }
}
