<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrelloConnection;
use App\Models\TrelloMember;
use App\Models\TrelloMemberBinding;
use App\Models\TrelloNotificationRule;
use App\Services\TrelloNotificationProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use TelegramBot\Contracts\TelegramAdapterInterface;
use Tests\TestCase;

class TrelloNotificationProcessorTest extends TestCase
{
    use RefreshDatabase;

    private TrelloConnection $connection;

    private TelegramAdapterInterface $telegram;

    private TrelloNotificationProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->connection = TrelloConnection::factory()->create([
            'api_key' => 'test-key',
            'api_token' => 'test-token',
        ]);

        $this->telegram = $this->createMock(TelegramAdapterInterface::class);

        $this->processor = new TrelloNotificationProcessor(
            app(HttpFactory::class),
            $this->telegram,
        );
    }

    public function test_ignores_non_update_card_actions(): void
    {
        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, [
            'action' => ['type' => 'createCard', 'data' => []],
        ]);
    }

    public function test_ignores_update_card_without_list_move(): void
    {
        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, [
            'action' => [
                'type' => 'updateCard',
                'data' => ['card' => ['id' => 'abc', 'name' => 'Test']],
            ],
        ]);
    }

    public function test_ignores_when_no_rule_matches_the_list(): void
    {
        TrelloNotificationRule::factory()->create([
            'connection_id' => $this->connection->id,
            'trigger_list_id' => 'list-other',
        ]);

        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-different'));
    }

    public function test_sends_notification_for_matching_rule(): void
    {
        TrelloNotificationRule::factory()->create([
            'connection_id' => $this->connection->id,
            'trigger_list_id' => 'list-abc',
            'telegram_chat_id' => '-1001234567',
            'message_template' => 'Карточка {{card_name}} перемещена в {{list_name}}',
        ]);

        $this->telegram->expects($this->once())
            ->method('sendMessage')
            ->with('-1001234567', 'Карточка Test Card перемещена в Done');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_label_filter_any_mode_matches_when_card_has_one_label(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response([
                'idLabels' => ['label-seo'],
                'idMembers' => [],
            ]),
        ]);

        TrelloNotificationRule::factory()
            ->withLabels(['label-seo', 'label-other'], 'any')
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
                'telegram_chat_id' => '-100111',
                'message_template' => 'ok',
            ]);

        $this->telegram->expects($this->once())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_label_filter_any_mode_skips_when_card_has_no_matching_labels(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response([
                'idLabels' => ['label-unrelated'],
                'idMembers' => [],
            ]),
        ]);

        TrelloNotificationRule::factory()
            ->withLabels(['label-seo'], 'any')
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
            ]);

        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_label_filter_all_mode_skips_when_card_missing_one_label(): void
    {
        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response([
                'idLabels' => ['label-seo'],
                'idMembers' => [],
            ]),
        ]);

        TrelloNotificationRule::factory()
            ->withLabels(['label-seo', 'label-design'], 'all')
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
            ]);

        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_member_filter_matches_when_card_has_matching_member(): void
    {
        $member = TrelloMember::factory()->create([
            'connection_id' => $this->connection->id,
            'trello_member_id' => 'trello-uid-123',
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response([
                'idLabels' => [],
                'idMembers' => ['trello-uid-123'],
            ]),
        ]);

        TrelloNotificationRule::factory()
            ->withMembers([$member->id])
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
                'telegram_chat_id' => '-100222',
                'message_template' => 'ok',
            ]);

        $this->telegram->expects($this->once())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_member_filter_skips_when_card_has_no_matching_member(): void
    {
        $member = TrelloMember::factory()->create([
            'connection_id' => $this->connection->id,
            'trello_member_id' => 'trello-uid-other',
        ]);

        Http::fake([
            'https://api.trello.com/1/cards/*' => Http::response([
                'idLabels' => [],
                'idMembers' => ['trello-uid-unrelated'],
            ]),
        ]);

        TrelloNotificationRule::factory()
            ->withMembers([$member->id])
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
            ]);

        $this->telegram->expects($this->never())->method('sendMessage');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_mentions_are_rendered_in_template(): void
    {
        $member = TrelloMember::factory()->create([
            'connection_id' => $this->connection->id,
        ]);

        TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $member->id,
            'telegram_username' => 'aniiahelpme',
        ]);

        TrelloNotificationRule::factory()
            ->withMentions([$member->id])
            ->create([
                'connection_id' => $this->connection->id,
                'trigger_list_id' => 'list-abc',
                'telegram_chat_id' => '-100333',
                'message_template' => 'Card moved {{mentions}}',
            ]);

        $this->telegram->expects($this->once())
            ->method('sendMessage')
            ->with('-100333', 'Card moved @aniiahelpme');

        $this->processor->process($this->connection, $this->makePayload('list-abc'));
    }

    public function test_template_variables_are_all_substituted(): void
    {
        TrelloNotificationRule::factory()->create([
            'connection_id' => $this->connection->id,
            'trigger_list_id' => 'list-abc',
            'telegram_chat_id' => '-100444',
            'message_template' => '{{card_name}} | {{list_name}} | {{board_name}} | {{card_url}}',
        ]);

        $this->telegram->expects($this->once())
            ->method('sendMessage')
            ->with('-100444', 'Test Card | Done | My Board | https://trello.com/c/short123');

        $this->processor->process($this->connection, $this->makePayload('list-abc', 'short123', 'My Board'));
    }

    private function makePayload(string $listId, string $shortLink = 'short123', string $boardName = 'My Board'): array
    {
        return [
            'action' => [
                'type' => 'updateCard',
                'data' => [
                    'card' => [
                        'id' => 'card-id-001',
                        'name' => 'Test Card',
                        'shortLink' => $shortLink,
                    ],
                    'listAfter' => [
                        'id' => $listId,
                        'name' => 'Done',
                    ],
                    'board' => [
                        'id' => 'board-id-001',
                        'name' => $boardName,
                    ],
                ],
            ],
        ];
    }
}
