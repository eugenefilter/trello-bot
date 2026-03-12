<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\TrelloConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature-тест команды `php artisan trello:sync {connection_id}`.
 *
 * Http::fake() вызывается ОДИН раз в setUp() с closures, которые читают
 * из $this->apiData. Метод fakeTrello() только обновляет данные.
 * Это необходимо потому что Http::fake() выполняет merge(), а не replace().
 *
 * RefreshDatabase гарантирует изоляцию данных между тестами.
 */
class SyncTrelloBoardTest extends TestCase
{
    use RefreshDatabase;

    private TrelloConnection $connection;

    /** Данные, возвращаемые фейковым Trello API. Обновляются через fakeTrello(). */
    private array $apiData = ['lists' => [], 'labels' => [], 'members' => []];

    protected function setUp(): void
    {
        parent::setUp();

        // Fake настраивается один раз — closures читают из $this->apiData
        Http::fake([
            'api.trello.com/1/boards/*/lists*' => fn () => Http::response($this->apiData['lists'], 200),
            'api.trello.com/1/boards/*/labels*' => fn () => Http::response($this->apiData['labels'], 200),
            'api.trello.com/1/boards/*/members*' => fn () => Http::response($this->apiData['members'], 200),
        ]);

        $this->connection = TrelloConnection::create([
            'name' => 'Test Board',
            'api_key' => 'test-key',
            'api_token' => 'test-token',
            'board_id' => 'board-abc',
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Создание/обновление списков
    // -------------------------------------------------------------------------

    /**
     * Команда создаёт записи trello_lists из ответа Trello API.
     */
    public function test_creates_lists_from_trello_api(): void
    {
        $this->fakeTrello(
            lists: [
                ['id' => 'list-1', 'name' => 'To Do'],
                ['id' => 'list-2', 'name' => 'In Progress'],
            ],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('trello_lists', [
            'connection_id' => $this->connection->id,
            'trello_list_id' => 'list-1',
            'name' => 'To Do',
            'board_id' => 'board-abc',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('trello_lists', [
            'trello_list_id' => 'list-2',
            'name' => 'In Progress',
        ]);
    }

    /**
     * Повторный запуск обновляет существующие записи, не создаёт дублей.
     */
    public function test_lists_are_idempotent(): void
    {
        $this->fakeTrello(
            lists: [['id' => 'list-1', 'name' => 'To Do']],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);

        $this->assertDatabaseCount('trello_lists', 1);
    }

    /**
     * Список, которого больше нет в Trello API, помечается is_active = false.
     */
    public function test_missing_lists_are_deactivated(): void
    {
        // Первый запуск — создаём два списка
        $this->fakeTrello(
            lists: [
                ['id' => 'list-1', 'name' => 'To Do'],
                ['id' => 'list-2', 'name' => 'Done'],
            ],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        // Второй запуск — list-2 исчез из API
        $this->fakeTrello(
            lists: [['id' => 'list-1', 'name' => 'To Do']],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->assertDatabaseHas('trello_lists', ['trello_list_id' => 'list-2', 'is_active' => false]);
        $this->assertDatabaseHas('trello_lists', ['trello_list_id' => 'list-1', 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // Создание/обновление меток
    // -------------------------------------------------------------------------

    /**
     * Команда создаёт записи trello_labels из ответа Trello API.
     */
    public function test_creates_labels_from_trello_api(): void
    {
        $this->fakeTrello(
            labels: [
                ['id' => 'label-1', 'name' => 'Bug', 'color' => 'red'],
                ['id' => 'label-2', 'name' => '',    'color' => 'blue'],  // метка без имени
            ],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('trello_labels', [
            'connection_id' => $this->connection->id,
            'trello_label_id' => 'label-1',
            'name' => 'Bug',
            'color' => 'red',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('trello_labels', [
            'trello_label_id' => 'label-2',
            'name' => null,
            'color' => 'blue',
        ]);
    }

    /**
     * Повторный запуск обновляет метки, не создаёт дублей.
     */
    public function test_labels_are_idempotent(): void
    {
        $this->fakeTrello(
            labels: [['id' => 'label-1', 'name' => 'Bug', 'color' => 'red']],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);

        $this->assertDatabaseCount('trello_labels', 1);
    }

    /**
     * Метка, которой нет в Trello API, помечается is_active = false.
     */
    public function test_missing_labels_are_deactivated(): void
    {
        $this->fakeTrello(
            labels: [
                ['id' => 'label-1', 'name' => 'Bug',     'color' => 'red'],
                ['id' => 'label-2', 'name' => 'Feature', 'color' => 'green'],
            ],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->fakeTrello(
            labels: [['id' => 'label-1', 'name' => 'Bug', 'color' => 'red']],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->assertDatabaseHas('trello_labels', ['trello_label_id' => 'label-2', 'is_active' => false]);
        $this->assertDatabaseHas('trello_labels', ['trello_label_id' => 'label-1', 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // Создание/обновление участников
    // -------------------------------------------------------------------------

    /**
     * Команда создаёт записи trello_members из ответа Trello API.
     */
    public function test_creates_members_from_trello_api(): void
    {
        $this->fakeTrello(
            members: [
                ['id' => 'member-1', 'username' => 'alice', 'fullName' => 'Alice Smith'],
                ['id' => 'member-2', 'username' => 'bob',   'fullName' => 'Bob Jones'],
            ],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])
            ->assertSuccessful();

        $this->assertDatabaseHas('trello_members', [
            'connection_id' => $this->connection->id,
            'trello_member_id' => 'member-1',
            'username' => 'alice',
            'full_name' => 'Alice Smith',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('trello_members', [
            'trello_member_id' => 'member-2',
            'username' => 'bob',
            'full_name' => 'Bob Jones',
        ]);
    }

    /**
     * Повторный запуск обновляет участников, не создаёт дублей.
     */
    public function test_members_are_idempotent(): void
    {
        $this->fakeTrello(
            members: [['id' => 'member-1', 'username' => 'alice', 'fullName' => 'Alice Smith']],
        );

        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id]);

        $this->assertDatabaseCount('trello_members', 1);
    }

    /**
     * Участник, которого нет в Trello API, помечается is_active = false.
     */
    public function test_missing_members_are_deactivated(): void
    {
        $this->fakeTrello(
            members: [
                ['id' => 'member-1', 'username' => 'alice', 'fullName' => 'Alice Smith'],
                ['id' => 'member-2', 'username' => 'bob',   'fullName' => 'Bob Jones'],
            ],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->fakeTrello(
            members: [['id' => 'member-1', 'username' => 'alice', 'fullName' => 'Alice Smith']],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->assertDatabaseHas('trello_members', ['trello_member_id' => 'member-2', 'is_active' => false]);
        $this->assertDatabaseHas('trello_members', ['trello_member_id' => 'member-1', 'is_active' => true]);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    /**
     * Несуществующий connection_id → команда завершается с ошибкой.
     */
    public function test_fails_when_connection_not_found(): void
    {
        $this->artisan('trello:sync', ['connection_id' => 9999])
            ->assertFailed();
    }

    /**
     * Обновляет название существующего списка если оно изменилось в Trello.
     */
    public function test_updates_list_name_on_resync(): void
    {
        $this->fakeTrello(
            lists: [['id' => 'list-1', 'name' => 'Old Name']],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->fakeTrello(
            lists: [['id' => 'list-1', 'name' => 'New Name']],
        );
        $this->artisan('trello:sync', ['connection_id' => $this->connection->id])->run();

        $this->assertDatabaseHas('trello_lists', ['trello_list_id' => 'list-1', 'name' => 'New Name']);
        $this->assertDatabaseMissing('trello_lists', ['name' => 'Old Name']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function fakeTrello(
        array $lists = [],
        array $labels = [],
        array $members = [],
    ): void {
        $this->apiData['lists'] = $lists;
        $this->apiData['labels'] = $labels;
        $this->apiData['members'] = $members;
    }
}
