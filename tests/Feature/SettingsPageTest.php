<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Pages\Settings;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create();
    }

    public function test_page_loads_successfully(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Settings::class)
            ->assertOk();
    }

    public function test_form_is_filled_with_current_settings_on_mount(): void
    {
        $this->actingAs($this->adminUser());

        AppSetting::set('log_cleanup_days', '14');
        AppSetting::set('log_cleanup_schedule', 'daily');

        Livewire::test(Settings::class)
            ->assertFormFieldExists('log_cleanup_days')
            ->assertFormFieldExists('log_cleanup_schedule')
            ->assertSchemaStateSet([
                'log_cleanup_days' => '14',
                'log_cleanup_schedule' => 'daily',
            ]);
    }

    public function test_form_uses_defaults_when_no_settings_exist(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Settings::class)
            ->assertSchemaStateSet([
                'log_cleanup_days' => '0',
                'log_cleanup_schedule' => 'weekly',
            ]);
    }

    public function test_save_persists_settings_to_app_settings(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Settings::class)
            ->fillForm([
                'log_cleanup_days' => '30',
                'log_cleanup_schedule' => 'monthly',
            ])
            ->call('save')
            ->assertNotified();

        $this->assertSame('30', AppSetting::get('log_cleanup_days'));
        $this->assertSame('monthly', AppSetting::get('log_cleanup_schedule'));
    }

    public function test_save_validates_log_cleanup_days_is_numeric(): void
    {
        $this->actingAs($this->adminUser());

        Livewire::test(Settings::class)
            ->fillForm([
                'log_cleanup_days' => 'not-a-number',
                'log_cleanup_schedule' => 'weekly',
            ])
            ->call('save')
            ->assertHasFormErrors(['log_cleanup_days']);
    }

    public function test_run_now_action_dispatches_artisan_command(): void
    {
        $this->actingAs($this->adminUser());

        Artisan::shouldReceive('call')
            ->once()
            ->with('logs:cleanup', ['--force' => true]);

        Livewire::test(Settings::class)
            ->callAction('runNow')
            ->assertNotified();
    }
}
