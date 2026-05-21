<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloConnections\Schemas;

use App\Services\TrelloWebhookService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as FormActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

class TrelloConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Настройки подключения')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('board_id')
                        ->label('ID доски Trello')
                        ->required()
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label('Активно')
                        ->default(true),
                    TextInput::make('api_key')
                        ->label('API Key')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('api_token')
                        ->label('API Token')
                        ->password()
                        ->revealable()
                        ->required()
                        ->maxLength(255),
                ]),

            Section::make('Вебхук Trello')
                ->description('Вебхук позволяет получать события с доски в реальном времени')
                ->columns(2)
                ->schema([
                    TextInput::make('webhook_id')
                        ->label('ID вебхука')
                        ->readOnly()
                        ->dehydrated(false)
                        ->placeholder('—'),
                    TextInput::make('webhook_registered_at')
                        ->label('Зарегистрирован')
                        ->readOnly()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d.m.Y H:i') : null),
                    TextInput::make('webhook_domain')
                        ->label('Домен сервера')
                        ->placeholder('https://myserver.com')
                        ->helperText('Только домен — путь к вебхуку подставится автоматически')
                        ->url()
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (TextInput $component) => $component->state(
                            rtrim(config('app.url'), '/')
                        ))
                        ->columnSpanFull(),
                    FormActions::make([
                        Action::make('registerWebhook')
                            ->label('Зарегистрировать вебхук')
                            ->icon('heroicon-o-signal')
                            ->color('success')
                            ->visible(fn (Get $get) => ! $get('webhook_id'))
                            ->action(function (Get $get, $record, $livewire, TrelloWebhookService $service): void {
                                try {
                                    $service->register($record, $get('webhook_domain') ?: null);
                                    $livewire->refreshFormData(['webhook_id', 'webhook_registered_at']);
                                    Notification::make()->title('Вебхук зарегистрирован')->success()->send();
                                } catch (Throwable $e) {
                                    Notification::make()->title('Ошибка регистрации')->body($e->getMessage())->danger()->send();
                                }
                            }),

                        Action::make('revokeWebhook')
                            ->label('Отключить вебхук')
                            ->icon('heroicon-o-x-circle')
                            ->color('danger')
                            ->visible(fn (Get $get) => (bool) $get('webhook_id'))
                            ->requiresConfirmation()
                            ->modalHeading('Отключить вебхук Trello')
                            ->modalDescription('Trello перестанет отправлять события. Уведомления работать не будут.')
                            ->action(function ($record, $livewire, TrelloWebhookService $service): void {
                                try {
                                    $service->revoke($record);
                                    $livewire->refreshFormData(['webhook_id', 'webhook_registered_at']);
                                    Notification::make()->title('Вебхук отключён')->warning()->send();
                                } catch (Throwable $e) {
                                    Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                                }
                            }),
                    ])->columnSpanFull(),
                ]),
        ]);
    }
}
