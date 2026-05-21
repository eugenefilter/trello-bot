<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloNotificationRules;

use App\Filament\Admin\Resources\TrelloNotificationRules\Pages\CreateTrelloNotificationRule;
use App\Filament\Admin\Resources\TrelloNotificationRules\Pages\EditTrelloNotificationRule;
use App\Filament\Admin\Resources\TrelloNotificationRules\Pages\ListTrelloNotificationRules;
use App\Filament\Admin\Resources\TrelloNotificationRules\Schemas\TrelloNotificationRuleForm;
use App\Filament\Admin\Resources\TrelloNotificationRules\Tables\TrelloNotificationRulesTable;
use App\Models\TrelloNotificationRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TrelloNotificationRuleResource extends Resource
{
    protected static ?string $model = TrelloNotificationRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static \UnitEnum|string|null $navigationGroup = 'Trello';

    protected static ?string $navigationLabel = 'Правила уведомлений';

    protected static ?string $modelLabel = 'Правило уведомлений';

    protected static ?string $pluralModelLabel = 'Правила уведомлений';

    public static function form(Schema $schema): Schema
    {
        return TrelloNotificationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrelloNotificationRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrelloNotificationRules::route('/'),
            'create' => CreateTrelloNotificationRule::route('/create'),
            'edit' => EditTrelloNotificationRule::route('/{record}/edit'),
        ];
    }
}
