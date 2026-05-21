<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\TrelloMembers\Pages\EditTrelloMember;
use App\Filament\Admin\Resources\TrelloMembers\Pages\ListTrelloMembers;
use App\Models\TrelloMember;
use App\Models\TrelloMemberBinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TrelloMemberBindingAdminTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create();
    }

    public function test_list_page_shows_members_with_binding_status(): void
    {
        $this->actingAs($this->adminUser());

        $memberWithBinding = TrelloMember::factory()->create();
        TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $memberWithBinding->id,
        ]);

        $memberWithoutBinding = TrelloMember::factory()->create();

        Livewire::test(ListTrelloMembers::class)
            ->assertCanSeeTableRecords([$memberWithBinding, $memberWithoutBinding]);
    }

    public function test_edit_page_loads_existing_binding_data(): void
    {
        $this->actingAs($this->adminUser());

        $member = TrelloMember::factory()->create();
        $binding = TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $member->id,
        ]);

        Livewire::test(EditTrelloMember::class, ['record' => $member->id])
            ->assertFormFieldExists('telegram_user_id')
            ->assertFormFieldExists('telegram_username')
            ->assertFormFieldExists('telegram_full_name');
    }

    public function test_saving_creates_binding_when_none_exists(): void
    {
        $this->actingAs($this->adminUser());

        $member = TrelloMember::factory()->create();

        Livewire::test(EditTrelloMember::class, ['record' => $member->id])
            ->fillForm([
                'telegram_user_id' => '123456789',
                'telegram_username' => 'testuser',
                'telegram_full_name' => 'Test User',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('trello_member_bindings', [
            'trello_member_id' => $member->id,
            'telegram_user_id' => '123456789',
            'telegram_username' => 'testuser',
            'telegram_full_name' => 'Test User',
        ]);
    }

    public function test_saving_updates_existing_binding(): void
    {
        $this->actingAs($this->adminUser());

        $member = TrelloMember::factory()->create();
        $binding = TrelloMemberBinding::factory()->withTelegram()->create([
            'trello_member_id' => $member->id,
        ]);

        Livewire::test(EditTrelloMember::class, ['record' => $member->id])
            ->fillForm([
                'telegram_user_id' => '999888777',
                'telegram_username' => 'newusername',
                'telegram_full_name' => 'New Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('trello_member_bindings', [
            'id' => $binding->id,
            'trello_member_id' => $member->id,
            'telegram_user_id' => '999888777',
            'telegram_username' => 'newusername',
        ]);

        $this->assertDatabaseCount('trello_member_bindings', 1);
    }

    public function test_saving_does_not_modify_trello_member_record(): void
    {
        $this->actingAs($this->adminUser());

        $member = TrelloMember::factory()->create([
            'full_name' => 'Original Name',
            'username' => 'originaluser',
        ]);

        Livewire::test(EditTrelloMember::class, ['record' => $member->id])
            ->fillForm([
                'telegram_user_id' => '123456789',
                'telegram_username' => 'telegramuser',
            ])
            ->call('save');

        $this->assertDatabaseHas('trello_members', [
            'id' => $member->id,
            'full_name' => 'Original Name',
            'username' => 'originaluser',
        ]);
    }
}
