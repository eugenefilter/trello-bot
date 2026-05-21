<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloConnections\Tables;

use App\Models\TrelloConnection;
use App\Services\TrelloWebhookService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class TrelloConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('board_id')
                    ->label('ID доски')
                    ->searchable(),
                TextColumn::make('api_key')
                    ->label('API Key')
                    ->limit(12)
                    ->tooltip(fn (TrelloConnection $record) => $record->api_key),
                IconColumn::make('webhook_id')
                    ->label('Вебхук')
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-signal-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (TrelloConnection $record) => $record->webhook_registered_at
                        ? 'Зарегистрирован '.$record->webhook_registered_at->format('d.m.Y H:i')
                        : 'Не зарегистрирован'
                    ),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('registerWebhook')
                    ->label('Подключить вебхук')
                    ->icon(Heroicon::OutlinedSignal)
                    ->color('success')
                    ->visible(fn (TrelloConnection $record) => ! $record->webhook_id)
                    ->modalHeading('Зарегистрировать вебхук Trello')
                    ->modalDescription('Укажите URL, на который Trello будет отправлять события доски.')
                    ->form(fn (TrelloConnection $record) => [
                        TextInput::make('webhook_domain')
                            ->label('Домен сервера')
                            ->default(rtrim(config('app.url'), '/'))
                            ->required()
                            ->url()
                            ->helperText('Только домен — путь к вебхуку подставится автоматически'),
                    ])
                    ->action(function (TrelloConnection $record, TrelloWebhookService $service, array $data): void {
                        try {
                            $service->register($record, $data['webhook_domain']);
                            Notification::make()->title('Вебхук подключён')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Ошибка регистрации')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('revokeWebhook')
                    ->label('Отключить вебхук')
                    ->icon(Heroicon::OutlinedSignalSlash)
                    ->color('danger')
                    ->visible(fn (TrelloConnection $record) => (bool) $record->webhook_id)
                    ->requiresConfirmation()
                    ->modalHeading('Отключить вебхук Trello')
                    ->modalDescription('Trello перестанет отправлять события. Уведомления работать не будут.')
                    ->action(function (TrelloConnection $record, TrelloWebhookService $service): void {
                        try {
                            $service->revoke($record);
                            Notification::make()->title('Вебхук отключён')->warning()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Ошибка')->body($e->getMessage())->danger()->send();
                        }
                    }),

                Action::make('sync')
                    ->label('Синхронизировать')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (TrelloConnection $record): void {
                        Artisan::call('trello:sync', ['connection_id' => $record->id]);
                        Notification::make()
                            ->title('Синхронизация завершена')
                            ->body("Доска {$record->board_id} синхронизирована.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
