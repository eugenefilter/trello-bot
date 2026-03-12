<?php

namespace App\Filament\Admin\Resources\TrelloLabels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrelloLabelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('connection_id')
                    ->relationship('connection', 'name')
                    ->required(),
                TextInput::make('trello_label_id')
                    ->required(),
                TextInput::make('board_id')
                    ->required(),
                TextInput::make('name')
                    ->default(null),
                TextInput::make('color')
                    ->default(null),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
