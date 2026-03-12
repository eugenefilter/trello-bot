<?php

namespace App\Filament\Admin\Resources\TrelloLists;

use App\Filament\Admin\Resources\TrelloLists\Pages\ListTrelloLists;
use App\Filament\Admin\Resources\TrelloLists\Tables\TrelloListsTable;
use App\Models\TrelloList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloListResource extends Resource
{
    protected static ?string $model = TrelloList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static \UnitEnum|string|null $navigationGroup = 'Trello';

    protected static ?string $navigationLabel = 'Списки';

    protected static ?string $modelLabel = 'Список';

    protected static ?string $pluralModelLabel = 'Списки';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return TrelloListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrelloLists::route('/'),
        ];
    }
}
