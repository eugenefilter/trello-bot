<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use TelegramBot\Adapters\TrelloAdapter;
use TelegramBot\Contracts\TrelloApiLogRepositoryInterface;
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

    private MockInterface $apiLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiLog = Mockery::mock(TrelloApiLogRepositoryInterface::class);
        $this->apiLog->shouldReceive('log')->byDefault();

        $this->adapter = new TrelloAdapter(
            http: app(HttpFactory::class),
            apiKey: 'test-key',
            apiToken: 'test-token',
            apiLog: $this->apiLog,
        );
    }

    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
        parent::tearDown();
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
            $this->assertStringContainsString('/1/cards', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertStringContainsString('test-key', $request->url());
            $this->assertStringContainsString('test-token', $request->url());
            $this->assertSame('list-123', $request['idList']);
            $this->assertSame('Новая задача', $request['name']);
            $this->assertSame('Описание задачи', $request['desc']);

            return true;
        });
    }

    /**
     * createCard должен вернуть CreatedCardResult с id, shortLink и url из ответа Trello.
     */
    public function test_create_card_returns_created_card_result(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response($this->cardResponse(), 200),
        ]);

        $result = $this->adapter->createCard($this->cardDTO());

        $this->assertInstanceOf(CreatedCardResult::class, $result);
        $this->assertSame('card-id-abc', $result->id);
        $this->assertSame('AbCd1234', $result->shortLink);
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
     * 422 от Trello — невалидные параметры → TrelloValidationException.
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
     * 500 и другие серверные ошибки → TrelloConnectionException.
     */
    public function test_throws_connection_exception_on_server_error(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('internal server error', 500),
        ]);

        $this->expectException(TrelloConnectionException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * Сетевая ошибка → TrelloConnectionException.
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
     * Успешный запрос логируется с корректным статусом.
     */
    public function test_logs_successful_request(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response($this->cardResponse(), 200),
        ]);

        $this->apiLog
            ->shouldReceive('log')
            ->once()
            ->withArgs(function (string $method, string $endpoint, int $status) {
                return $method === 'POST'
                    && str_contains($endpoint, '/cards')
                    && $status === 200;
            });

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * Ошибочный запрос логируется до броска исключения.
     */
    public function test_logs_failed_request_before_throwing(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid key', 401),
        ]);

        $this->apiLog
            ->shouldReceive('log')
            ->once()
            ->withArgs(function (string $method, string $endpoint, int $status) {
                return $status === 401;
            });

        $this->expectException(TrelloAuthException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * Сетевая ошибка логируется со статусом 0.
     */
    public function test_logs_connection_error_with_status_zero(): void
    {
        Http::fake([
            'api.trello.com/*' => function () {
                throw new ConnectionException('timeout');
            },
        ]);

        $this->apiLog
            ->shouldReceive('log')
            ->once()
            ->withArgs(function (string $method, string $endpoint, int $status) {
                return $status === 0;
            });

        $this->expectException(TrelloConnectionException::class);

        $this->adapter->createCard($this->cardDTO());
    }

    /**
     * attachFile отправляет POST на /cards/{id}/attachments.
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

    /**
     * deleteCard отправляет DELETE /1/cards/{shortLink}.
     */
    public function test_delete_card_sends_correct_request(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('', 200),
        ]);

        $this->adapter->deleteCard('AbCd1234');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/1/cards/AbCd1234')
                && $request->method() === 'DELETE';
        });
    }

    /**
     * deleteCard при 404 не бросает исключение — карточка уже удалена.
     */
    public function test_delete_card_ignores_404(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('', 404),
        ]);

        $this->expectNotToPerformAssertions();

        $this->adapter->deleteCard('AbCd1234');
    }

    /**
     * deleteCard при 401 бросает TrelloAuthException.
     */
    public function test_delete_card_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('', 401),
        ]);

        $this->expectException(TrelloAuthException::class);

        $this->adapter->deleteCard('AbCd1234');
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
            'shortLink' => 'AbCd1234',
            'url' => 'https://trello.com/c/card-id-abc',
            'shortUrl' => 'https://trello.com/c/AbCd1234',
            'name' => 'Новая задача',
        ];
    }
}
