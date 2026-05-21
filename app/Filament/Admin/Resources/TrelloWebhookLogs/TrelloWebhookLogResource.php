<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloWebhookLogs;

use App\Filament\Admin\Resources\TrelloWebhookLogs\Pages\ListTrelloWebhookLogs;
use App\Filament\Admin\Resources\TrelloWebhookLogs\Pages\ViewTrelloWebhookLog;
use App\Models\TrelloWebhookLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrelloWebhookLogResource extends Resource
{
    protected static ?string $model = TrelloWebhookLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?string $navigationLabel = 'Trello Webhooks';

    protected static ?string $modelLabel = 'Webhook от Trello';

    protected static ?string $pluralModelLabel = 'Trello Webhook логи';

    protected static ?int $navigationSort = 4;

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
                TextColumn::make('connection.name')
                    ->label('Подключение')
                    ->searchable(),
                TextColumn::make('action_type')
                    ->label('Тип события')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'updateCard' => 'success',
                        'createCard' => 'info',
                        'deleteCard' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('received_at')
                    ->label('Получен')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s')
            ->filters([
                SelectFilter::make('action_type')
                    ->label('Тип события')
                    ->options([
                        'updateCard' => 'updateCard',
                        'createCard' => 'createCard',
                        'deleteCard' => 'deleteCard',
                        'commentCard' => 'commentCard',
                    ]),
            ])
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
            'index' => ListTrelloWebhookLogs::route('/'),
            'view' => ViewTrelloWebhookLog::route('/{record}'),
        ];
    }
}
