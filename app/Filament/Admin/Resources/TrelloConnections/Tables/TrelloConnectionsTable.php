<?php

namespace App\Filament\Admin\Resources\TrelloConnections\Tables;

use App\Models\TrelloConnection;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

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
