<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrelloConnection;
use App\Models\TrelloNotificationRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrelloNotificationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_rule_with_required_fields(): void
    {
        $rule = TrelloNotificationRule::factory()->create();

        $this->assertDatabaseHas('trello_notification_rules', [
            'id' => $rule->id,
            'is_active' => true,
        ]);
    }

    public function test_json_fields_are_cast_to_arrays(): void
    {
        $rule = TrelloNotificationRule::factory()
            ->withLabels(['abc123', 'def456'])
            ->withMembers([1, 2])
            ->withMentions([3])
            ->create();

        $this->assertIsArray($rule->filter_label_ids);
        $this->assertIsArray($rule->filter_member_binding_ids);
        $this->assertIsArray($rule->mention_member_binding_ids);
        $this->assertContains('abc123', $rule->filter_label_ids);
    }

    public function test_null_json_fields_remain_null(): void
    {
        $rule = TrelloNotificationRule::factory()->create();

        $this->assertNull($rule->filter_label_ids);
        $this->assertNull($rule->filter_member_binding_ids);
        $this->assertNull($rule->mention_member_binding_ids);
    }

    public function test_rule_belongs_to_connection(): void
    {
        $connection = TrelloConnection::factory()->create();
        $rule = TrelloNotificationRule::factory()->create([
            'connection_id' => $connection->id,
        ]);

        $this->assertEquals($connection->id, $rule->connection->id);
    }

    public function test_rule_is_deleted_when_connection_is_deleted(): void
    {
        $connection = TrelloConnection::factory()->create();
        $rule = TrelloNotificationRule::factory()->create([
            'connection_id' => $connection->id,
        ]);

        $connection->delete();

        $this->assertDatabaseMissing('trello_notification_rules', ['id' => $rule->id]);
    }

    public function test_inactive_state(): void
    {
        $rule = TrelloNotificationRule::factory()->inactive()->create();

        $this->assertFalse($rule->is_active);
    }
}
