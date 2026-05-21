<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrelloConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

class TrelloWebhookService
{
    private const string TRELLO_API = 'https://api.trello.com/1';

    public function __construct(private readonly HttpFactory $http) {}

    public function register(TrelloConnection $connection, ?string $domain = null): void
    {
        $base = rtrim($domain ?? config('app.url'), '/');
        $callbackUrl = "{$base}/webhooks/trello/{$connection->webhook_token}";

        $boardId = $this->resolveBoardId($connection);

        $response = $this->http
            ->withQueryParameters([
                'key' => $connection->api_key,
                'token' => $connection->api_token,
            ])
            ->post(self::TRELLO_API.'/webhooks', [
                'callbackURL' => $callbackUrl,
                'idModel' => $boardId,
                'description' => "Trello Bot: {$connection->name}",
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->body());
        }

        $connection->update([
            'webhook_id' => $response->json('id'),
            'webhook_registered_at' => now(),
        ]);
    }

    /**
     * Если board_id — короткая ссылка (не 24-символьный hex), резолвим настоящий ID через API.
     */
    private function resolveBoardId(TrelloConnection $connection): string
    {
        if (ctype_xdigit($connection->board_id) && strlen($connection->board_id) === 24) {
            return $connection->board_id;
        }

        $response = $this->http
            ->withQueryParameters([
                'key' => $connection->api_key,
                'token' => $connection->api_token,
                'fields' => 'id',
            ])
            ->get(self::TRELLO_API."/boards/{$connection->board_id}");

        if ($response->failed()) {
            throw new RuntimeException("Не удалось получить ID доски: {$response->body()}");
        }

        return $response->json('id');
    }

    public function revoke(TrelloConnection $connection): void
    {
        if (! $connection->webhook_id) {
            return;
        }

        $this->http
            ->withQueryParameters([
                'key' => $connection->api_key,
                'token' => $connection->api_token,
            ])
            ->delete(self::TRELLO_API."/webhooks/{$connection->webhook_id}");

        $connection->update([
            'webhook_id' => null,
            'webhook_registered_at' => null,
        ]);
    }
}
