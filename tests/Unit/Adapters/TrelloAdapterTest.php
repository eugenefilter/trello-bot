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
use TelegramBot\DTOs\AttachmentResult;
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
            'api.trello.com/*' => Http::response(['id' => 'attachment-1', 'url' => 'https://trello.com/attach/1'], 200),
        ]);

        $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('/cards/card-xyz/attachments', $request->url());
            $this->assertSame('POST', $request->method());

            return true;
        });
    }

    /**
     * attachFile возвращает AttachmentResult с id и url из ответа Trello.
     */
    public function test_attach_file_returns_attachment_result(): void
    {
        $tmpFile = $this->createTmpFile();

        Http::fake([
            'api.trello.com/*' => Http::response([
                'id' => 'attachment-1',
                'url' => 'https://trello-attachments.s3.amazonaws.com/abc/photo.jpg',
            ], 200),
        ]);

        $result = $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        $this->assertInstanceOf(AttachmentResult::class, $result);
        $this->assertSame('attachment-1', $result->id);
        $this->assertSame('https://trello-attachments.s3.amazonaws.com/abc/photo.jpg', $result->url);
    }

    /**
     * attachFile возвращает AttachmentResult с url=null если url отсутствует в ответе.
     */
    public function test_attach_file_returns_result_with_null_url_when_url_absent(): void
    {
        $tmpFile = $this->createTmpFile();

        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'attachment-1'], 200),
        ]);

        $result = $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        $this->assertInstanceOf(AttachmentResult::class, $result);
        $this->assertSame('attachment-1', $result->id);
        $this->assertNull($result->url);
    }

    /**
     * attachFile возвращает null если id отсутствует в ответе Trello.
     */
    public function test_attach_file_returns_null_when_id_absent(): void
    {
        $tmpFile = $this->createTmpFile();

        Http::fake([
            'api.trello.com/*' => Http::response(['url' => 'https://trello-attachments.s3.amazonaws.com/abc/photo.jpg'], 200),
        ]);

        $result = $this->adapter->attachFile('card-xyz', $tmpFile, 'image/jpeg');

        $this->assertNull($result);
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
     * updateCard отправляет PUT /1/cards/{id} с name и desc.
     */
    public function test_update_card_sends_put_request_with_name_and_description(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'card-id-abc'], 200),
        ]);

        $this->adapter->updateCard('card-id-abc', 'Новое название', 'Новое описание');

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('/1/cards/card-id-abc', $request->url());
            $this->assertSame('PUT', $request->method());
            $this->assertSame('Новое название', $request['name']);
            $this->assertSame('Новое описание', $request['desc']);

            return true;
        });
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

    /**
     * addComment отправляет POST /1/cards/{id}/actions/comments с текстом.
     */
    public function test_add_comment_sends_post_to_correct_endpoint(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'comment-1'], 200),
        ]);

        $this->adapter->addComment('card-id-abc', 'Это комментарий к карточке');

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('/1/cards/card-id-abc/actions/comments', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame('Это комментарий к карточке', $request['text']);

            return true;
        });
    }

    /**
     * addComment возвращает id добавленного комментария (Trello action ID).
     */
    public function test_add_comment_returns_action_id(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response(['id' => 'action-id-xyz'], 200),
        ]);

        $actionId = $this->adapter->addComment('card-id-abc', 'Текст');

        $this->assertSame('action-id-xyz', $actionId);
    }

    /**
     * addComment возвращает null если id отсутствует в ответе.
     */
    public function test_add_comment_returns_null_when_id_absent(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response(['type' => 'commentCard'], 200),
        ]);

        $actionId = $this->adapter->addComment('card-id-abc', 'Текст');

        $this->assertNull($actionId);
    }

    /**
     * addComment при ошибке 401 бросает TrelloAuthException.
     */
    public function test_add_comment_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid key', 401),
        ]);

        $this->expectException(TrelloAuthException::class);

        $this->adapter->addComment('card-id-abc', 'Текст');
    }

    /**
     * deleteComment отправляет DELETE /1/actions/{id}.
     */
    public function test_delete_comment_sends_correct_request(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('', 200),
        ]);

        $this->adapter->deleteComment('action-id-xyz');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/1/actions/action-id-xyz')
                && $request->method() === 'DELETE';
        });
    }

    /**
     * deleteComment при 401 бросает TrelloAuthException.
     */
    public function test_delete_comment_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid key', 401),
        ]);

        $this->expectException(TrelloAuthException::class);

        $this->adapter->deleteComment('action-id-xyz');
    }

    /**
     * deleteAttachment отправляет DELETE /1/cards/{cardId}/attachments/{attachmentId}.
     */
    public function test_delete_attachment_sends_correct_request(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('', 200),
        ]);

        $this->adapter->deleteAttachment('card-id-abc', 'att-id-xyz');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/1/cards/card-id-abc/attachments/att-id-xyz')
                && $request->method() === 'DELETE';
        });
    }

    /**
     * deleteAttachment при 401 бросает TrelloAuthException.
     */
    public function test_delete_attachment_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'api.trello.com/*' => Http::response('invalid key', 401),
        ]);

        $this->expectException(TrelloAuthException::class);

        $this->adapter->deleteAttachment('card-id-abc', 'att-id-xyz');
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
