<?php

namespace App\Filament\Admin\Resources\TrelloMembers;

use App\Filament\Admin\Resources\TrelloMembers\Pages\ListTrelloMembers;
use App\Filament\Admin\Resources\TrelloMembers\Tables\TrelloMembersTable;
use App\Models\TrelloMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloMemberResource extends Resource
{
    protected static ?string $model = TrelloMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static \UnitEnum|string|null $navigationGroup = 'Trello';

    protected static ?string $navigationLabel = 'Участники';

    protected static ?string $modelLabel = 'Участник';

    protected static ?string $pluralModelLabel = 'Участники';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return TrelloMembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrelloMembers::route('/'),
        ];
    }
}
