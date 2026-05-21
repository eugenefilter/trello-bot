<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TrelloMember;
use App\Models\TrelloMemberBinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrelloMemberBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_binding_without_telegram_data(): void
    {
        $member = TrelloMember::factory()->create();

        $binding = TrelloMemberBinding::factory()->create([
            'trello_member_id' => $member->id,
        ]);

        $this->assertDatabaseHas('trello_member_bindings', [
            'trello_member_id' => $member->id,
            'telegram_user_id' => null,
            'telegram_username' => null,
        ]);

        $this->assertNull($binding->telegram_user_id);
        $this->assertNull($binding->telegram_username);
    }

    public function test_can_create_binding_with_telegram_data(): void
    {
        $member = TrelloMember::factory()->create();

        $binding = TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $member->id,
        ]);

        $this->assertDatabaseHas('trello_member_bindings', [
            'trello_member_id' => $member->id,
        ]);

        $this->assertNotNull($binding->telegram_user_id);
        $this->assertNotNull($binding->telegram_username);
        $this->assertNotNull($binding->telegram_full_name);
    }

    public function test_binding_belongs_to_trello_member(): void
    {
        $member = TrelloMember::factory()->create();
        $binding = TrelloMemberBinding::factory()->create([
            'trello_member_id' => $member->id,
        ]);

        $this->assertEquals($member->id, $binding->trelloMember->id);
        $this->assertEquals($member->username, $binding->trelloMember->username);
    }

    public function test_trello_member_has_one_binding(): void
    {
        $member = TrelloMember::factory()->create();
        $binding = TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $member->id,
        ]);

        $this->assertEquals($binding->id, $member->binding->id);
        $this->assertEquals($binding->telegram_username, $member->binding->telegram_username);
    }

    public function test_binding_is_deleted_when_member_is_deleted(): void
    {
        $member = TrelloMember::factory()->create();
        $binding = TrelloMemberBinding::factory()->create([
            'trello_member_id' => $member->id,
        ]);

        $member->delete();

        $this->assertDatabaseMissing('trello_member_bindings', ['id' => $binding->id]);
    }
}
