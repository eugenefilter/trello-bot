<?php

namespace App\Filament\Admin\Resources\TrelloLabels;

use App\Filament\Admin\Resources\TrelloLabels\Pages\ListTrelloLabels;
use App\Filament\Admin\Resources\TrelloLabels\Tables\TrelloLabelsTable;
use App\Models\TrelloLabel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloLabelResource extends Resource
{
    protected static ?string $model = TrelloLabel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static \UnitEnum|string|null $navigationGroup = 'Trello';

    protected static ?string $navigationLabel = 'Метки';

    protected static ?string $modelLabel = 'Метка';

    protected static ?string $pluralModelLabel = 'Метки';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return TrelloLabelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrelloLabels::route('/'),
        ];
    }
}
