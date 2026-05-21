<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrelloConnection;
use App\Services\TrelloWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class TrelloWebhookRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_request_to_webhook_url_returns_200(): void
    {
        $connection = TrelloConnection::factory()->create();

        $this->call('HEAD', "/webhooks/trello/{$connection->webhook_token}")
            ->assertOk();
    }

    public function test_register_saves_webhook_id_and_timestamp(): void
    {
        Http::fake([
            'https://api.trello.com/1/boards/*' => Http::response(['id' => str_repeat('a', 24)], 200),
            'https://api.trello.com/1/webhooks*' => Http::response(['id' => 'webhook-abc-123'], 200),
        ]);

        $connection = TrelloConnection::factory()->create();

        app(TrelloWebhookService::class)->register($connection);

        $this->assertDatabaseHas('trello_connections', [
            'id' => $connection->id,
            'webhook_id' => 'webhook-abc-123',
        ]);
        $this->assertNotNull($connection->fresh()->webhook_registered_at);
    }

    public function test_register_sends_correct_payload_to_trello(): void
    {
        $fullBoardId = str_repeat('b', 24);

        Http::fake([
            'https://api.trello.com/1/boards/*' => Http::response(['id' => $fullBoardId], 200),
            'https://api.trello.com/1/webhooks*' => Http::response(['id' => 'webhook-xyz'], 200),
        ]);

        $connection = TrelloConnection::factory()->create([
            'board_id' => 'board123',
            'api_key' => 'mykey',
            'api_token' => 'mytoken',
        ]);

        app(TrelloWebhookService::class)->register($connection);

        Http::assertSent(function (Request $request) use ($fullBoardId, $connection): bool {
            return str_contains($request->url(), 'api.trello.com/1/webhooks')
                && $request['idModel'] === $fullBoardId
                && str_contains($request['callbackURL'], "/webhooks/trello/{$connection->webhook_token}");
        });
    }

    public function test_register_throws_on_trello_api_error(): void
    {
        Http::fake([
            'https://api.trello.com/1/webhooks*' => Http::response('invalid token', 401),
        ]);

        $connection = TrelloConnection::factory()->create();

        $this->expectException(RuntimeException::class);

        app(TrelloWebhookService::class)->register($connection);
    }

    public function test_revoke_clears_webhook_fields(): void
    {
        Http::fake([
            'https://api.trello.com/1/webhooks/*' => Http::response('', 200),
        ]);

        $connection = TrelloConnection::factory()->create([
            'webhook_id' => 'webhook-to-delete',
            'webhook_registered_at' => now(),
        ]);

        app(TrelloWebhookService::class)->revoke($connection);

        $this->assertDatabaseHas('trello_connections', [
            'id' => $connection->id,
            'webhook_id' => null,
        ]);
        $this->assertNull($connection->fresh()->webhook_registered_at);
    }

    public function test_revoke_does_nothing_when_no_webhook(): void
    {
        Http::fake();

        $connection = TrelloConnection::factory()->create(['webhook_id' => null]);

        app(TrelloWebhookService::class)->revoke($connection);

        Http::assertNothingSent();
    }
}
