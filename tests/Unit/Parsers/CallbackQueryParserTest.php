<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use TelegramBot\DTOs\CallbackAction;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Parsers\TelegramUpdateParser;
use Tests\TestCase;

/**
 * Unit-тест парсинга callback_query update и CallbackAction.
 */
class CallbackQueryParserTest extends TestCase
{
    private TelegramUpdateParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TelegramUpdateParser;
    }

    /**
     * Парсинг callback_query возвращает TelegramCallbackDTO с корректными полями.
     */
    public function test_parses_callback_query_into_dto(): void
    {
        $payload = $this->callbackPayload('delete:AbCd1234');

        $result = $this->parser->parseCallback($payload);

        $this->assertInstanceOf(TelegramCallbackDTO::class, $result);
        $this->assertSame('cq-999', $result->callbackId);
        $this->assertSame('100', $result->chatId);
        $this->assertSame(42, $result->messageId);
        $this->assertSame('delete:AbCd1234', $result->data);
        $this->assertSame('ru', $result->languageCode);
    }

    /**
     * parseCallback возвращает null если payload не является callback_query.
     */
    public function test_returns_null_for_non_callback_payload(): void
    {
        $payload = ['message' => ['text' => 'Привет']];

        $result = $this->parser->parseCallback($payload);

        $this->assertNull($result);
    }

    /**
     * CallbackAction корректно парсит "delete:AbCd1234".
     */
    public function test_callback_action_parses_delete_action(): void
    {
        $action = CallbackAction::fromData('delete:AbCd1234');

        $this->assertSame('delete', $action->action);
        $this->assertSame('AbCd1234', $action->payload);
    }

    /**
     * CallbackAction с неизвестным форматом возвращает null.
     */
    public function test_callback_action_returns_null_for_unknown_format(): void
    {
        $action = CallbackAction::fromData('invalid');

        $this->assertNull($action);
    }

    private function callbackPayload(string $data): array
    {
        return [
            'callback_query' => [
                'id' => 'cq-999',
                'data' => $data,
                'from' => [
                    'language_code' => 'ru',
                ],
                'message' => [
                    'message_id' => 42,
                    'chat' => ['id' => 100],
                ],
            ],
        ];
    }
}
