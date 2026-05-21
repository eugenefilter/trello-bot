<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloMembers\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TrelloMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trello')
                ->columns(2)
                ->schema([
                    Placeholder::make('connection_name')
                        ->label('Подключение')
                        ->content(fn ($record) => $record?->connection?->name ?? '—'),
                    Placeholder::make('trello_member_id')
                        ->label('Trello ID')
                        ->content(fn ($record) => $record?->trello_member_id ?? '—'),
                    Placeholder::make('full_name')
                        ->label('Имя')
                        ->content(fn ($record) => $record?->full_name ?? '—'),
                    Placeholder::make('username')
                        ->label('Username')
                        ->content(fn ($record) => $record ? "@{$record->username}" : '—'),
                ]),

            Section::make('Telegram')
                ->description('Заполните данные для связи с пользователем Telegram')
                ->columns(2)
                ->schema([
                    TextInput::make('telegram_user_id')
                        ->label('Telegram ID')
                        ->numeric()
                        ->placeholder('123456789'),
                    TextInput::make('telegram_username')
                        ->label('Telegram @username')
                        ->prefix('@')
                        ->placeholder('username'),
                    TextInput::make('telegram_full_name')
                        ->label('Отображаемое имя')
                        ->placeholder('Имя Фамилия')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
