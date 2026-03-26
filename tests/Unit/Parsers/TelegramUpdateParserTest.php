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

    /**
     * language_code пользователя извлекается из from.language_code.
     */
    public function test_extracts_language_code(): void
    {
        $update = $this->textUpdate('hi');
        $update['message']['from']['language_code'] = 'uk';

        $dto = $this->parser->parse($update);

        $this->assertSame('uk', $dto->languageCode);
    }

    /**
     * Отсутствие language_code → null.
     */
    public function test_language_code_is_null_when_absent(): void
    {
        $dto = $this->parser->parse($this->textUpdate('hi'));

        $this->assertNull($dto->languageCode);
    }

    /**
     * reply_to_message с фото и caption → replyToMessage заполнен.
     */
    public function test_extracts_reply_to_message_with_photo_and_caption(): void
    {
        $dto = $this->parser->parse($this->replyToPhotoUpdate());

        $this->assertNotNull($dto->replyToMessage);
        $this->assertSame('на укр версії не все так однозначно', $dto->replyToMessage->caption);
        $this->assertNull($dto->replyToMessage->text);
        $this->assertSame('reply_file_id_large', $dto->replyToMessage->photos[0]);
    }

    /**
     * reply_to_message с текстом → replyToMessage->text заполнен.
     */
    public function test_extracts_reply_to_message_with_text(): void
    {
        $dto = $this->parser->parse($this->replyToTextUpdate('Исходный текст поста'));

        $this->assertNotNull($dto->replyToMessage);
        $this->assertSame('Исходный текст поста', $dto->replyToMessage->text);
        $this->assertNull($dto->replyToMessage->caption);
        $this->assertEmpty($dto->replyToMessage->photos);
    }

    /**
     * reply_to_message с документом → replyToMessage->documents заполнен.
     */
    public function test_extracts_reply_to_message_with_document(): void
    {
        $dto = $this->parser->parse($this->replyToDocumentUpdate());

        $this->assertNotNull($dto->replyToMessage);
        $this->assertSame('reply_document_file_id', $dto->replyToMessage->documents[0]);
        $this->assertEmpty($dto->replyToMessage->photos);
        $this->assertSame('/bug Съехал текст', $dto->replyToMessage->caption);
    }

    /**
     * Без reply_to_message → replyToMessage равен null.
     */
    public function test_reply_to_message_is_null_when_absent(): void
    {
        $dto = $this->parser->parse($this->textUpdate('Hello'));

        $this->assertNull($dto->replyToMessage);
    }

    public function test_extracts_media_group_id_when_present(): void
    {
        $update = $this->photoUpdate();
        $update['message']['media_group_id'] = 'group-xyz-999';

        $dto = $this->parser->parse($update);

        $this->assertSame('group-xyz-999', $dto->mediaGroupId);
    }

    public function test_media_group_id_is_null_for_regular_message(): void
    {
        $dto = $this->parser->parse($this->textUpdate('Hello'));

        $this->assertNull($dto->mediaGroupId);
    }

    /** Сообщение-ответ на пост с фото — как в реальном update от пользователя */
    private function replyToPhotoUpdate(): array
    {
        return [
            'update_id' => 294206881,
            'message' => [
                'message_id' => 6368,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                'date' => 1773319167,
                'reply_to_message' => [
                    'message_id' => 6352,
                    'from' => ['id' => 327010592, 'username' => 'Chiz_Han', 'first_name' => 'Anton'],
                    'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                    'date' => 1773145361,
                    'photo' => [
                        ['file_id' => 'reply_file_id_small',  'file_unique_id' => 'r1', 'width' => 90,   'height' => 51],
                        ['file_id' => 'reply_file_id_medium', 'file_unique_id' => 'r2', 'width' => 320,  'height' => 183],
                        ['file_id' => 'reply_file_id_large',  'file_unique_id' => 'r3', 'width' => 1280, 'height' => 731],
                    ],
                    'caption' => 'на укр версії не все так однозначно',
                ],
                'text' => '/bug Нет перевода на украинский',
                'entities' => [
                    ['offset' => 0, 'length' => 4, 'type' => 'bot_command'],
                ],
            ],
        ];
    }

    /** Сообщение-ответ на текстовый пост */
    private function replyToTextUpdate(string $replyText): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => 200,
                'from' => ['id' => 111111, 'username' => 'testuser', 'first_name' => 'Test'],
                'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                'date' => 1773319167,
                'reply_to_message' => [
                    'message_id' => 199,
                    'from' => ['id' => 999, 'username' => 'author', 'first_name' => 'Author'],
                    'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                    'date' => 1773145361,
                    'text' => $replyText,
                ],
                'text' => '/bug описание бага',
                'entities' => [
                    ['offset' => 0, 'length' => 4, 'type' => 'bot_command'],
                ],
            ],
        ];
    }

    /** Сообщение-ответ на сообщение с документом (фото как файл) */
    private function replyToDocumentUpdate(): array
    {
        return [
            'update_id' => 294206938,
            'message' => [
                'message_id' => 6427,
                'from' => ['id' => 746276963, 'username' => 'eugeneoleinykov', 'first_name' => 'Eugene'],
                'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                'date' => 1774541865,
                'reply_to_message' => [
                    'message_id' => 6404,
                    'from' => ['id' => 111868151, 'username' => 'Gushilov', 'first_name' => 'Ivan'],
                    'chat' => ['id' => -1001888188920, 'type' => 'supergroup'],
                    'date' => 1774348333,
                    'document' => [
                        'file_name' => 'image_2026-03-24_12-32-14.png',
                        'mime_type' => 'image/png',
                        'file_id' => 'reply_document_file_id',
                        'file_unique_id' => 'AgAD2pwAAqmlGUo',
                        'file_size' => 107885,
                    ],
                    'caption' => '/bug Съехал текст',
                    'caption_entities' => [
                        ['offset' => 0, 'length' => 4, 'type' => 'bot_command'],
                    ],
                ],
                'text' => '/bug@itsell_trello_bot',
                'entities' => [
                    ['offset' => 0, 'length' => 22, 'type' => 'bot_command'],
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
