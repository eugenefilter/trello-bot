<?php

namespace App\Filament\Admin\Resources\TelegramRequestLogs;

use App\Filament\Admin\Resources\TelegramRequestLogs\Pages\ListTelegramRequestLogs;
use App\Filament\Admin\Resources\TelegramRequestLogs\Pages\ViewTelegramRequestLog;
use App\Models\TelegramRequestLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramRequestLogResource extends Resource
{
    protected static ?string $model = TelegramRequestLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?string $navigationLabel = 'Входящие запросы';

    protected static ?string $modelLabel = 'Запрос';

    protected static ?string $pluralModelLabel = 'Входящие запросы';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('payload')
                    ->label('Тип')
                    ->getStateUsing(function ($record) {
                        $payload = $record->payload;

                        if (isset($payload['message'])) {
                            return 'message';
                        }
                        if (isset($payload['edited_message'])) {
                            return 'edited_message';
                        }
                        if (isset($payload['channel_post'])) {
                            return 'channel_post';
                        }
                        if (isset($payload['callback_query'])) {
                            return 'callback_query';
                        }
                        if (isset($payload['inline_query'])) {
                            return 'inline_query';
                        }

                        return 'unknown';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'message' => 'success',
                        'edited_message' => 'warning',
                        'unknown' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('update_id')
                    ->label('Update ID')
                    ->getStateUsing(fn ($record) => $record->payload['update_id'] ?? '—'),
                TextColumn::make('chat_id')
                    ->label('Chat ID')
                    ->getStateUsing(function ($record) {
                        $payload = $record->payload;

                        return $payload['message']['chat']['id']
                            ?? $payload['edited_message']['chat']['id']
                            ?? $payload['channel_post']['chat']['id']
                            ?? '—';
                    }),
                TextColumn::make('text')
                    ->label('Текст')
                    ->placeholder('—')
                    ->limit(80)
                    ->getStateUsing(function ($record) {
                        $payload = $record->payload;

                        return $payload['message']['text']
                            ?? $payload['message']['caption']
                            ?? $payload['edited_message']['text']
                            ?? $payload['channel_post']['text']
                            ?? null;
                    }),
                TextColumn::make('received_at')
                    ->label('Получено')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('3s')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramRequestLogs::route('/'),
            'view' => ViewTelegramRequestLog::route('/{record}'),
        ];
    }
}
