<?php

namespace App\Filament\Admin\Resources\TrelloConnections;

use App\Filament\Admin\Resources\TrelloConnections\Pages\CreateTrelloConnection;
use App\Filament\Admin\Resources\TrelloConnections\Pages\EditTrelloConnection;
use App\Filament\Admin\Resources\TrelloConnections\Pages\ListTrelloConnections;
use App\Filament\Admin\Resources\TrelloConnections\Schemas\TrelloConnectionForm;
use App\Filament\Admin\Resources\TrelloConnections\Tables\TrelloConnectionsTable;
use App\Models\TrelloConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloConnectionResource extends Resource
{
    protected static ?string $model = TrelloConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Trello';

    protected static ?string $navigationLabel = 'Подключения';

    protected static ?string $modelLabel = 'Подключение';

    protected static ?string $pluralModelLabel = 'Подключения';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return TrelloConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrelloConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTrelloConnections::route('/'),
            'create' => CreateTrelloConnection::route('/create'),
            'edit'   => EditTrelloConnection::route('/{record}/edit'),
        ];
    }
}
