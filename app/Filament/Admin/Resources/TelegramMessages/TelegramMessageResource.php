<?php

namespace App\Filament\Admin\Resources\TelegramMessages;

use App\Filament\Admin\Resources\TelegramMessages\Pages\ListTelegramMessages;
use App\Filament\Admin\Resources\TelegramMessages\Tables\TelegramMessagesTable;
use App\Models\TelegramMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelegramMessageResource extends Resource
{
    protected static ?string $model = TelegramMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static \UnitEnum|string|null $navigationGroup = 'Логи';

    protected static ?string $navigationLabel = 'Telegram сообщения';

    protected static ?string $modelLabel = 'Сообщение';

    protected static ?string $pluralModelLabel = 'Telegram сообщения';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return TelegramMessagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramMessages::route('/'),
        ];
    }
}
