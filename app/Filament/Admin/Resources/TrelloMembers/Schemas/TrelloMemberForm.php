<?php

namespace App\Filament\Admin\Resources\TrelloMembers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TrelloMemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('connection_id')
                    ->relationship('connection', 'name')
                    ->required(),
                TextInput::make('trello_member_id')
                    ->required(),
                TextInput::make('username')
                    ->required(),
                TextInput::make('full_name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
