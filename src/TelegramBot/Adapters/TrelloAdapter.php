<?php

declare(strict_types=1);

namespace TelegramBot\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloAuthException;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Exceptions\TrelloValidationException;

/**
 * Реализация клиента Trello REST API v1.
 *
 * HTTP-клиент инжектируется через конструктор — это позволяет
 * использовать Http::fake() в тестах без реальных запросов к Trello.
 * Биндинг настраивается в AppServiceProvider.
 */
class TrelloAdapter implements TrelloAdapterInterface
{
    private const string BASE_URL = 'https://api.trello.com/1';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly string $apiToken,
    ) {}

    /**
     * Создаёт карточку в указанном списке Trello.
     *
     * @throws TrelloAuthException при 401 — невалидные credentials
     * @throws TrelloValidationException при 422 — несуществующий list/label/member
     * @throws TrelloConnectionException при сетевой ошибке
     */
    public function createCard(TrelloCardDTO $dto): CreatedCardResult
    {
        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->post(self::BASE_URL.'/cards', [
                    'idList' => $dto->listId,
                    'name' => $dto->name,
                    'desc' => $dto->description,
                    'idMembers' => implode(',', $dto->memberIds),
                    'idLabels' => implode(',', $dto->labelIds),
                ]);
        } catch (ConnectionException $e) {
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->handleErrors($response);

        $body = $response->json();

        return new CreatedCardResult(
            id: $body['id'],
            url: $body['url'],
        );
    }

    /**
     * Прикрепляет файл к карточке через multipart/form-data запрос.
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function attachFile(string $cardId, string $filePath, string $mimeType): void
    {
        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
                ->post(self::BASE_URL."/cards/{$cardId}/attachments");
        } catch (ConnectionException $e) {
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->handleErrors($response);

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Назначает участников на карточку.
     *
     * @param  string[]  $memberIds
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function addMembersToCard(string $cardId, array $memberIds): void
    {
        foreach ($memberIds as $memberId) {
            try {
                $response = $this->http
                    ->withQueryParameters($this->credentials())
                    ->post(self::BASE_URL."/cards/{$cardId}/idMembers", [
                        'value' => $memberId,
                    ]);
            } catch (ConnectionException $e) {
                throw new TrelloConnectionException($e->getMessage(), previous: $e);
            }

            $this->handleErrors($response);
        }
    }

    /**
     * Добавляет метки на карточку.
     *
     * @param  string[]  $labelIds
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function addLabelsToCard(string $cardId, array $labelIds): void
    {
        foreach ($labelIds as $labelId) {
            try {
                $response = $this->http
                    ->withQueryParameters($this->credentials())
                    ->post(self::BASE_URL."/cards/{$cardId}/idLabels", [
                        'value' => $labelId,
                    ]);
            } catch (ConnectionException $e) {
                throw new TrelloConnectionException($e->getMessage(), previous: $e);
            }

            $this->handleErrors($response);
        }
    }

    /**
     * Возвращает все списки доски для синхронизации справочника trello_lists.
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function getBoardLists(string $boardId): array
    {
        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->get(self::BASE_URL."/boards/{$boardId}/lists");
        } catch (ConnectionException $e) {
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->handleErrors($response);

        return $response->json();
    }

    /**
     * Возвращает все метки доски для синхронизации справочника trello_labels.
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function getBoardLabels(string $boardId): array
    {
        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->get(self::BASE_URL."/boards/{$boardId}/labels");
        } catch (ConnectionException $e) {
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->handleErrors($response);

        return $response->json();
    }

    /**
     * Возвращает всех участников доски для синхронизации справочника trello_members.
     *
     * @throws TrelloAuthException
     * @throws TrelloConnectionException
     */
    public function getBoardMembers(string $boardId): array
    {
        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->get(self::BASE_URL."/boards/{$boardId}/members");
        } catch (ConnectionException $e) {
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->handleErrors($response);

        return $response->json();
    }

    /**
     * Формирует query-параметры для аутентификации в Trello API.
     */
    private function credentials(): array
    {
        return [
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ];
    }

    /**
     * Проверяет HTTP-статус ответа и выбрасывает доменное исключение.
     *
     * @throws TrelloAuthException при 401
     * @throws TrelloValidationException при 422
     */
    private function handleErrors(Response $response): void
    {
        match (true) {
            $response->status() === 401 => throw new TrelloAuthException(
                'Trello auth failed: invalid api_key or api_token'
            ),
            $response->status() === 422 => throw new TrelloValidationException(
                'Trello validation error: '.$response->body()
            ),
            default => null,
        };
    }
}
