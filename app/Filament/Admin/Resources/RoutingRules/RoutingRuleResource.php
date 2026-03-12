<?php

namespace App\Filament\Admin\Resources\RoutingRules;

use App\Filament\Admin\Resources\RoutingRules\Pages\CreateRoutingRule;
use App\Filament\Admin\Resources\RoutingRules\Pages\EditRoutingRule;
use App\Filament\Admin\Resources\RoutingRules\Pages\ListRoutingRules;
use App\Filament\Admin\Resources\RoutingRules\Schemas\RoutingRuleForm;
use App\Filament\Admin\Resources\RoutingRules\Tables\RoutingRulesTable;
use App\Models\RoutingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoutingRuleResource extends Resource
{
    protected static ?string $model = RoutingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static \UnitEnum|string|null $navigationGroup = 'Бот';

    protected static ?string $navigationLabel = 'Правила маршрутизации';

    protected static ?string $modelLabel = 'Правило';

    protected static ?string $pluralModelLabel = 'Правила маршрутизации';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return RoutingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoutingRulesTable::configure($table);
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
            'index' => ListRoutingRules::route('/'),
            'create' => CreateRoutingRule::route('/create'),
            'edit' => EditRoutingRule::route('/{record}/edit'),
        ];
    }
}
