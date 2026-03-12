<?php

namespace App\Filament\Admin\Resources\TrelloCardLogs;

use App\Filament\Admin\Resources\TrelloCardLogs\Pages\ListTrelloCardLogs;
use App\Filament\Admin\Resources\TrelloCardLogs\Tables\TrelloCardLogsTable;
use App\Models\TrelloCardLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloCardLogResource extends Resource
{
    protected static ?string $model = TrelloCardLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?string $navigationLabel = 'Карточки Trello';

    protected static ?string $modelLabel = 'Лог карточки';

    protected static ?string $pluralModelLabel = 'Логи карточек';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return TrelloCardLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrelloCardLogs::route('/'),
        ];
    }
}
