<?php

namespace App\Filament\Admin\Resources\TrelloConnections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrelloConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                TextInput::make('board_id')
                    ->label('ID доски Trello')
                    ->required()
                    ->maxLength(255),
                TextInput::make('api_key')
                    ->label('API Key')
                    ->required()
                    ->maxLength(255),
                TextInput::make('api_token')
                    ->label('API Token')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Активно')
                    ->default(true),
            ]);
    }
}
