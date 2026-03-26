<?php

declare(strict_types=1);

namespace TelegramBot\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\Contracts\TrelloApiLogRepositoryInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloAuthException;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Exceptions\TrelloValidationException;

/**
 * Реализация клиента Trello REST API v1.
 *
 * Каждый HTTP-вызов логируется через TrelloApiLogRepositoryInterface:
 *   — метод, endpoint, HTTP-статус, тело ответа (только для ошибок), время выполнения.
 * Это позволяет отлаживать интеграцию через админ-панель без grep по логам.
 */
class TrelloAdapter implements TrelloAdapterInterface
{
    private const string BASE_URL = 'https://api.trello.com/1';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
        private readonly string $apiToken,
        private readonly TrelloApiLogRepositoryInterface $apiLog,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function createCard(TrelloCardDTO $dto): CreatedCardResult
    {
        $response = $this->call('POST', '/cards', fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->post(self::BASE_URL.'/cards', [
                'idList' => $dto->listId,
                'name' => $dto->name,
                'desc' => $dto->description,
                'idMembers' => implode(',', $dto->memberIds),
                'idLabels' => implode(',', $dto->labelIds),
            ])
        );

        $body = $response->json();

        return new CreatedCardResult(
            id: $body['id'],
            shortLink: $body['shortLink'],
            url: $body['url'],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function attachFile(string $cardId, string $filePath, string $mimeType): void
    {
        $this->call('POST', "/cards/{$cardId}/attachments", fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
            ->post(self::BASE_URL."/cards/{$cardId}/attachments")
        );

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addMembersToCard(string $cardId, array $memberIds): void
    {
        foreach ($memberIds as $memberId) {
            $this->call('POST', "/cards/{$cardId}/idMembers", fn () => $this->http
                ->withQueryParameters($this->credentials())
                ->post(self::BASE_URL."/cards/{$cardId}/idMembers", ['value' => $memberId])
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function addLabelsToCard(string $cardId, array $labelIds): void
    {
        foreach ($labelIds as $labelId) {
            $this->call('POST', "/cards/{$cardId}/idLabels", fn () => $this->http
                ->withQueryParameters($this->credentials())
                ->post(self::BASE_URL."/cards/{$cardId}/idLabels", ['value' => $labelId])
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateCard(string $cardId, string $name, string $description): void
    {
        $this->call('PUT', "/cards/{$cardId}", fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->put(self::BASE_URL."/cards/{$cardId}", [
                'name' => $name,
                'desc' => $description,
            ])
        );
    }

    /**
     * {@inheritDoc}
     */
    public function deleteCard(string $shortLink): void
    {
        $start = microtime(true);

        try {
            $response = $this->http
                ->withQueryParameters($this->credentials())
                ->delete(self::BASE_URL."/cards/{$shortLink}");
        } catch (ConnectionException $e) {
            $this->apiLog->log('DELETE', "/cards/{$shortLink}", 0, $e->getMessage(), $this->elapsed($start));
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $this->apiLog->log('DELETE', "/cards/{$shortLink}", $response->status(), null, $this->elapsed($start));

        if ($response->status() === 404) {
            return;
        }

        $this->handleErrors($response);
    }

    /**
     * {@inheritDoc}
     */
    public function getBoardLists(string $boardId): array
    {
        $response = $this->call('GET', "/boards/{$boardId}/lists", fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->get(self::BASE_URL."/boards/{$boardId}/lists")
        );

        return $response->json();
    }

    /**
     * {@inheritDoc}
     */
    public function getBoardLabels(string $boardId): array
    {
        $response = $this->call('GET', "/boards/{$boardId}/labels", fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->get(self::BASE_URL."/boards/{$boardId}/labels")
        );

        return $response->json();
    }

    /**
     * {@inheritDoc}
     */
    public function getBoardMembers(string $boardId): array
    {
        $response = $this->call('GET', "/boards/{$boardId}/members", fn () => $this->http
            ->withQueryParameters($this->credentials())
            ->get(self::BASE_URL."/boards/{$boardId}/members")
        );

        return $response->json();
    }

    /**
     * Выполняет HTTP-вызов, логирует результат и пробрасывает исключения.
     *
     * @param  callable(): Response  $fn
     *
     * @throws TrelloAuthException
     * @throws TrelloValidationException
     * @throws TrelloConnectionException
     */
    private function call(string $method, string $endpoint, callable $fn): Response
    {
        $start = microtime(true);

        try {
            $response = $fn();
        } catch (ConnectionException $e) {
            $this->apiLog->log($method, $endpoint, 0, $e->getMessage(), $this->elapsed($start));
            throw new TrelloConnectionException($e->getMessage(), previous: $e);
        }

        $isError = $response->failed();

        $this->apiLog->log(
            $method,
            $endpoint,
            $response->status(),
            $isError ? mb_substr($response->body(), 0, 2000) : null,
            $this->elapsed($start),
        );

        $this->handleErrors($response);

        return $response;
    }

    private function elapsed(float $start): int
    {
        return (int) ((microtime(true) - $start) * 1000);
    }

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
     * @throws TrelloConnectionException при любом другом 4xx/5xx
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
            $response->failed() => throw new TrelloConnectionException(
                'Trello API error '.$response->status().': '.$response->body()
            ),
            default => null,
        };
    }
}
