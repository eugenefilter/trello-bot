<?php

namespace App\Filament\Admin\Resources\TrelloApiLogs;

use App\Filament\Admin\Resources\TrelloApiLogs\Pages\ListTrelloApiLogs;
use App\Filament\Admin\Resources\TrelloApiLogs\Pages\ViewTrelloApiLog;
use App\Models\TrelloApiLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TrelloApiLogResource extends Resource
{
    protected static ?string $model = TrelloApiLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?string $navigationLabel = 'Trello API';

    protected static ?string $modelLabel = 'Запрос к Trello';

    protected static ?string $pluralModelLabel = 'Trello API логи';

    protected static ?int $navigationSort = 3;

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
                TextColumn::make('method')
                    ->label('Метод')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'GET' => 'info',
                        'POST' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('endpoint')
                    ->label('Endpoint')
                    ->limit(60),
                TextColumn::make('http_status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state === 0 => 'danger',
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 400 => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('duration_ms')
                    ->label('Время (мс)')
                    ->sortable(),
                TextColumn::make('response_body')
                    ->label('Ответ (ошибка)')
                    ->placeholder('—')
                    ->limit(80)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Время')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->poll('3s')
            ->filters([
                SelectFilter::make('method')
                    ->label('Метод')
                    ->options(['GET' => 'GET', 'POST' => 'POST']),
                SelectFilter::make('http_status')
                    ->label('Статус')
                    ->options([
                        '200' => '200 OK',
                        '401' => '401 Auth error',
                        '422' => '422 Validation',
                        '500' => '500 Server error',
                        '0' => 'Connection error',
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
            'index' => ListTrelloApiLogs::route('/'),
            'view' => ViewTrelloApiLog::route('/{record}'),
        ];
    }
}
