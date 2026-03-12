<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Models\AppSetting;
use App\Models\TrelloApiLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TelegramBot\Contracts\TrelloApiLogRepositoryInterface;
use Tests\TestCase;

class EloquentTrelloApiLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TrelloApiLogRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = app(TrelloApiLogRepositoryInterface::class);
    }

    public function test_logs_when_setting_enabled(): void
    {
        AppSetting::setBool('trello_api_logging', true);

        $this->repository->log('GET', '/boards/abc', 200, null, 50);

        $this->assertDatabaseCount('trello_api_logs', 1);
    }

    public function test_does_not_log_when_setting_disabled(): void
    {
        AppSetting::setBool('trello_api_logging', false);

        $this->repository->log('GET', '/boards/abc', 200, null, 50);

        $this->assertDatabaseCount('trello_api_logs', 0);
    }

    public function test_logs_by_default_when_no_setting_exists(): void
    {
        $this->repository->log('GET', '/boards/abc', 200, null, 50);

        $this->assertDatabaseCount('trello_api_logs', 1);
    }

    public function test_truncates_response_body_to_2000_chars(): void
    {
        $this->repository->log('POST', '/cards', 422, str_repeat('x', 3000), 100);

        $log = TrelloApiLog::first();

        $this->assertSame(2000, mb_strlen($log->response_body));
    }
}
