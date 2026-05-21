<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessTrelloWebhookJob;
use App\Models\TrelloConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrelloWebhookHandleTest extends TestCase
{
    use RefreshDatabase;

    private TrelloConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->connection = TrelloConnection::factory()->create([
            'is_active' => true,
        ]);
    }

    public function test_head_request_returns_200(): void
    {
        $response = $this->call(
            'HEAD',
            "/webhooks/trello/{$this->connection->webhook_token}",
        );

        $response->assertStatus(200);
    }

    public function test_post_dispatches_job_and_returns_ok(): void
    {
        $payload = ['action' => ['type' => 'updateCard']];

        $response = $this->postJson(
            "/webhooks/trello/{$this->connection->webhook_token}",
            $payload,
        );

        $response->assertOk()->assertJson(['ok' => true]);

        Queue::assertPushed(ProcessTrelloWebhookJob::class, function (ProcessTrelloWebhookJob $job) use ($payload) {
            $reflected = new \ReflectionClass($job);

            $connId = $reflected->getProperty('connectionId');
            $connId->setAccessible(true);

            $pl = $reflected->getProperty('payload');
            $pl->setAccessible(true);

            return $connId->getValue($job) === $this->connection->id
                && json_decode($pl->getValue($job), true) === $payload;
        });
    }

    public function test_post_does_not_dispatch_job_for_inactive_connection(): void
    {
        $inactive = TrelloConnection::factory()->create(['is_active' => false]);

        $this->postJson(
            "/webhooks/trello/{$inactive->webhook_token}",
            ['action' => ['type' => 'updateCard']],
        )->assertOk();

        Queue::assertNotPushed(ProcessTrelloWebhookJob::class);
    }

    public function test_unknown_token_returns_404(): void
    {
        $this->postJson('/webhooks/trello/non-existent-token', [])
            ->assertNotFound();
    }
}
