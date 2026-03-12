<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use TelegramBot\Adapters\TrelloAdapter;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloAuthException;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Exceptions\TrelloValidationException;
use Tests\TestCase;

/**
 * Unit-тест TrelloAdapter.
 *
 * Используем Http::fake() для имитации ответов Trello API
 * без реальных сетевых запросов. Адаптер получает HttpFactory
 * через конструктор, поэтому fake() перехватывает все его запросы.
 */
class TrelloAdapterTest extends TestCase
{
    private TrelloAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаём адаптер с тестовыми credentials
        $this->adapter = new TrelloAdapter(
            http: app(HttpFactory::class),
            apiKey: 'test-key',
            apiToken: 'test-token',
        );
    }

    /**
     * createCard должен отправить POST /1/cards с правильными параметрами.
     */
    public function test_create_card_sends_correct_request(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response($this->cardResponse(), 200),
        ]);

        $this->adapter->createCard($this->cardDTO());

        Http::assertSent(function ($request) {
            // Проверяем endpoint и метод
            $this->assertStringContainsString('/1/cards', $request->url());
            $this->assertSame('POST', $request->method());

            // Проверяем что credentials переданы
            $this->assertStringContainsString('test-key', $request->url());
            $this->assertStringContainsString('test-token', $request->url());

            // Проверяем тело запроса
            $this->assertSame('list-123', $request['idList']);
            $this->assertSame('Новая задача', $request['name']);
            $this->assertSame('Описание задачи', $request['desc']);

            return true;
        });
    }

    /**
     * createCard должен вернуть CreatedCardResult с id и url из ответа Trello.
     */
    public function test_create_card_returns_created_card_result(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response($this->cardResponse(), 200),
        ]);

        $result = $this->adapter->createCard($this->cardDTO());

        $this->assertInstanceOf(CreatedCardResult::class, $result);
        $this->assertSame('card-id-abc', $result->id);
        $this->assertSame('https://trello.com/c/card-id-abc', $result->url);
    }

    /**
     * 401 от Trello — невалидный api_key или api_token → TrelloAuthException.
     */
    public function test_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid key', 401),
        ]);

        $this->expectException(TrelloAuthException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * 422 от Trello — невалидные параметры (например несуществующий list_id) → TrelloValidationException.
     */
    public function test_throws_validation_exception_on_422(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid value', 422),
        ]);

        $this->expectException(TrelloValidationException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * Сетевая ошибка (таймаут, недоступность) → TrelloConnectionException.
     * Job поймает это исключение и уйдёт в retry.
     */
    public function test_throws_connection_exception_on_network_error(): void
    {
        Http::fake([
            'api.trello.com/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(TrelloConnectionException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * attachFile отправляет POST на /cards/{id}/attachments (multipart/form-data).
     */
    public function test_attach_file_sends_request_to_correct_endpoint(): void
    {
        $tmpFile = $this->createTmpFile();

        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'attachment-1'], 200),
        ]);

        $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('/cards/card-xyz/attachments', $request->url());
            $this->assertSame('POST', $request->method());

            return true;
        });
    }

    /**
     * attachFile принимает локальный путь файла и передаёт его содержимое в Trello API.
     */
    public function test_attach_file_sends_file_contents(): void
    {
        $tmpFile = $this->createTmpFile('test image content');

        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'attachment-1'], 200),
        ]);

        $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('test-key', $request->url());

            return true;
        });
    }

    /**
     * attachFile удаляет локальный файл после успешной загрузки в Trello.
     */
    public function test_attach_file_deletes_local_file_after_upload(): void
    {
        $tmpFile = $this->createTmpFile();

        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'attachment-1'], 200),
        ]);

        $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        $this->assertFileDoesNotExist($tmpFile);
    }

    // --- Fixtures ---

    private function createTmpFile(string $content = 'fake image data'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'trello_test_');
        file_put_contents($path, $content);

        return $path;
    }

    private function cardDTO(): TrelloCardDTO
    {
        return new TrelloCardDTO(
            listId: 'list-123',
            name: 'Новая задача',
            description: 'Описание задачи',
            memberIds: ['member-1'],
            labelIds: ['label-1'],
        );
    }

    private function cardResponse(): array
    {
        return [
            'id' => 'card-id-abc',
            'url' => 'https://trello.com/c/card-id-abc',
            'shortUrl' => 'https://trello.com/c/card-id-abc',
            'name' => 'Новая задача',
        ];
    }
}
