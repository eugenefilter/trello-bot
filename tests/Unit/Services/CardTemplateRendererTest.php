<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TelegramBot\DTOs\ForwardOriginDTO;
use TelegramBot\DTOs\ReplyMessageDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\Services\CardTemplateRenderer;

/**
 * Unit-тест CardTemplateRenderer.
 *
 * Чистый PHP-тест — никаких зависимостей от Laravel.
 * Проверяет замену переменных шаблона значениями из TelegramMessageDTO.
 */
class CardTemplateRendererTest extends TestCase
{
    private CardTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new CardTemplateRenderer;
    }

    /**
     * {{first_name}} заменяется именем пользователя.
     */
    public function test_replaces_first_name(): void
    {
        $result = $this->renderer->render('Привет, {{first_name}}!', $this->makeDTO());

        $this->assertSame('Привет, Иван!', $result);
    }

    /**
     * {{username}} заменяется никнеймом пользователя.
     */
    public function test_replaces_username(): void
    {
        $result = $this->renderer->render('User: @{{username}}', $this->makeDTO());

        $this->assertSame('User: @ivanov', $result);
    }

    /**
     * {{user_id}} заменяется числовым ID пользователя.
     */
    public function test_replaces_user_id(): void
    {
        $result = $this->renderer->render('ID: {{user_id}}', $this->makeDTO());

        $this->assertSame('ID: 123456', $result);
    }

    /**
     * {{date}} заменяется датой отправки в формате дд.мм.гггг.
     */
    public function test_replaces_date(): void
    {
        $result = $this->renderer->render('{{date}}', $this->makeDTO());

        $this->assertSame('15.06.2024', $result);
    }

    /**
     * {{time}} заменяется временем отправки в формате чч:мм.
     */
    public function test_replaces_time(): void
    {
        $result = $this->renderer->render('{{time}}', $this->makeDTO());

        $this->assertSame('14:30', $result);
    }

    /**
     * {{text_preview}} заменяется первыми 80 символами текста.
     */
    public function test_replaces_text_preview_with_first_80_chars(): void
    {
        $longText = str_repeat('А', 100);
        $dto = $this->makeDTO(text: $longText);

        $result = $this->renderer->render('{{text_preview}}', $dto);

        $this->assertSame(str_repeat('А', 80), $result);
        $this->assertSame(80, mb_strlen($result));
    }

    /**
     * {{text_preview}} корректно работает с коротким текстом (меньше 80 символов).
     */
    public function test_replaces_text_preview_short_text(): void
    {
        $result = $this->renderer->render('{{text_preview}}', $this->makeDTO());

        $this->assertSame('Привет, мир!', $result);
    }

    /**
     * {{text}} заменяется полным текстом сообщения.
     */
    public function test_replaces_text_with_full_text(): void
    {
        $result = $this->renderer->render('Текст: {{text}}', $this->makeDTO());

        $this->assertSame('Текст: Привет, мир!', $result);
    }

    /**
     * {{chat_type}} заменяется типом чата.
     */
    public function test_replaces_chat_type(): void
    {
        $result = $this->renderer->render('({{chat_type}})', $this->makeDTO());

        $this->assertSame('(private)', $result);
    }

    /**
     * Несколько переменных заменяются одновременно.
     */
    public function test_replaces_multiple_variables(): void
    {
        $template = '{{first_name}} (@{{username}}) — {{date}} {{time}}';

        $result = $this->renderer->render($template, $this->makeDTO());

        $this->assertSame('Иван (@ivanov) — 15.06.2024 14:30', $result);
    }

    /**
     * Неизвестные переменные остаются как есть.
     */
    public function test_unknown_variables_remain_unchanged(): void
    {
        $result = $this->renderer->render('{{unknown}} {{first_name}}', $this->makeDTO());

        $this->assertSame('{{unknown}} Иван', $result);
    }

    /**
     * Если firstName = null, вставляется пустая строка.
     */
    public function test_null_first_name_renders_as_empty_string(): void
    {
        $dto = $this->makeDTO(firstName: null);
        $result = $this->renderer->render('{{first_name}}', $dto);

        $this->assertSame('', $result);
    }

    /**
     * Если текст null, для text и text_preview вставляется пустая строка.
     */
    public function test_null_text_renders_as_empty_string(): void
    {
        $dto = $this->makeDTO(text: null);
        $result = $this->renderer->render('{{text}} / {{text_preview}}', $dto);

        $this->assertSame(' / ', $result);
    }

    /**
     * Если текст null, но есть caption — используется caption.
     */
    public function test_uses_caption_when_text_is_null(): void
    {
        $dto = $this->makeDTO(text: null, caption: 'Подпись к фото');

        $result = $this->renderer->render('{{text}}', $dto);

        $this->assertSame('Подпись к фото', $result);
    }

    /**
     * Команда (/bug) обрезается из {{text}} и {{text_preview}}.
     */
    public function test_strips_command_from_text(): void
    {
        $dto = $this->makeDTO(text: '/bug Текст задачи', command: '/bug');

        $this->assertSame('Текст задачи', $this->renderer->render('{{text}}', $dto));
        $this->assertSame('Текст задачи', $this->renderer->render('{{text_preview}}', $dto));
    }

    /**
     * Если текст состоит только из команды — {{text}} пустой.
     */
    public function test_text_is_empty_when_only_command(): void
    {
        $dto = $this->makeDTO(text: '/bug', command: '/bug');

        $this->assertSame('', $this->renderer->render('{{text}}', $dto));
    }

    /**
     * {{reply_text}} заменяется caption из reply_to_message.
     */
    public function test_renders_reply_text_from_reply_caption(): void
    {
        $reply = new ReplyMessageDTO(
            text: null,
            caption: 'на укр версії не все так однозначно',
            photos: [],
        );
        $dto = $this->makeDTO(replyToMessage: $reply);

        $result = $this->renderer->render('Цитата: {{reply_text}}', $dto);

        $this->assertSame('Цитата: на укр версії не все так однозначно', $result);
    }

    /**
     * {{reply_text}} заменяется text из reply_to_message.
     */
    public function test_renders_reply_text_from_reply_text(): void
    {
        $reply = new ReplyMessageDTO(
            text: 'Оригинальный текст поста',
            caption: null,
            photos: [],
        );
        $dto = $this->makeDTO(replyToMessage: $reply);

        $result = $this->renderer->render('{{reply_text}}', $dto);

        $this->assertSame('Оригинальный текст поста', $result);
    }

    /**
     * {{reply_text}} пустой когда нет replyToMessage.
     */
    public function test_reply_text_is_empty_when_no_reply(): void
    {
        $result = $this->renderer->render('Цитата: {{reply_text}}', $this->makeDTO());

        $this->assertSame('Цитата: ', $result);
    }

    /**
     * {{forward_first_name}} заменяется именем оригинального отправителя.
     */
    public function test_replaces_forward_first_name(): void
    {
        $forward = new ForwardOriginDTO(type: 'user', firstName: 'Александр', username: 'Alex_itsellopt', userId: 579219779);
        $dto = $this->makeDTO(forwardOrigin: $forward);

        $result = $this->renderer->render('Переслал: {{forward_first_name}}', $dto);

        $this->assertSame('Переслал: Александр', $result);
    }

    /**
     * {{forward_username}} заменяется никнеймом оригинального отправителя.
     */
    public function test_replaces_forward_username(): void
    {
        $forward = new ForwardOriginDTO(type: 'user', firstName: 'Александр', username: 'Alex_itsellopt', userId: 579219779);
        $dto = $this->makeDTO(forwardOrigin: $forward);

        $result = $this->renderer->render('@{{forward_username}}', $dto);

        $this->assertSame('@Alex_itsellopt', $result);
    }

    /**
     * {{forward_first_name}} и {{forward_username}} пустые когда сообщение не переслано.
     */
    public function test_forward_variables_are_empty_when_not_forwarded(): void
    {
        $result = $this->renderer->render('{{forward_first_name}} / {{forward_username}}', $this->makeDTO());

        $this->assertSame(' / ', $result);
    }

    /**
     * {{forward_username}} пустой когда у оригинального отправителя нет username (hidden_user).
     */
    public function test_forward_username_is_empty_when_null(): void
    {
        $forward = new ForwardOriginDTO(type: 'hidden_user', firstName: 'Скрытый', username: null, userId: null);
        $dto = $this->makeDTO(forwardOrigin: $forward);

        $result = $this->renderer->render('{{forward_username}}', $dto);

        $this->assertSame('', $result);
    }

    // --- Fixtures ---

    private function makeDTO(
        ?string $text = 'Привет, мир!',
        ?string $caption = null,
        ?string $firstName = 'Иван',
        ?string $command = null,
        ?ReplyMessageDTO $replyToMessage = null,
        ?ForwardOriginDTO $forwardOrigin = null,
    ): TelegramMessageDTO {
        return new TelegramMessageDTO(
            messageType: 'text',
            text: $text,
            caption: $caption,
            photos: [],
            documents: [],
            userId: 123456,
            chatId: '999',
            chatType: 'private',
            command: $command,
            username: 'ivanov',
            firstName: $firstName,
            sentAt: new DateTimeImmutable('2024-06-15 14:30:00'),
            replyToMessage: $replyToMessage,
            forwardOrigin: $forwardOrigin,
        );
    }
}
