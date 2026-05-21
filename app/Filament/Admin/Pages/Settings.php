<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class Settings extends Page
{
    protected string $view = 'filament.admin.pages.settings';

    protected static ?string $navigationLabel = 'Настройки';

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Настройки';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'log_cleanup_days' => AppSetting::get('log_cleanup_days', '0'),
            'log_cleanup_schedule' => AppSetting::get('log_cleanup_schedule', 'weekly'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Section::make('Очистка логов')
                        ->description('Автоматически удалять старые записи из истории запросов и вебхуков.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('log_cleanup_days')
                                ->label('Хранить (дней)')
                                ->helperText('0 — очистка отключена')
                                ->numeric()
                                ->minValue(0)
                                ->default('0')
                                ->required(),
                            Select::make('log_cleanup_schedule')
                                ->label('Расписание')
                                ->options([
                                    'daily' => 'Ежедневно',
                                    'weekly' => 'Еженедельно',
                                    'monthly' => 'Ежемесячно',
                                ])
                                ->default('weekly')
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Сохранить')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::set('log_cleanup_days', (string) ($data['log_cleanup_days'] ?? '0'));
        AppSetting::set('log_cleanup_schedule', $data['log_cleanup_schedule'] ?? 'weekly');

        Notification::make()
            ->success()
            ->title('Настройки сохранены')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runNow')
                ->label('Запустить сейчас')
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Запустить очистку логов')
                ->modalDescription('Будут удалены все записи старше указанного количества дней. Это действие нельзя отменить.')
                ->action(function (): void {
                    Artisan::call('logs:cleanup', ['--force' => true]);

                    Notification::make()
                        ->success()
                        ->title('Очистка запущена')
                        ->send();
                }),
        ];
    }
}
