<?php

namespace App\Filament\Admin\Resources\TrelloLists\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrelloListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('connection_id')
                    ->relationship('connection', 'name')
                    ->required(),
                TextInput::make('trello_list_id')
                    ->required(),
                TextInput::make('board_id')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
