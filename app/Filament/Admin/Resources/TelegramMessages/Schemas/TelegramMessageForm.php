<?php

namespace App\Filament\Admin\Resources\TelegramMessages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelegramMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('update_id')->label('Update ID')->disabled(),
                TextInput::make('chat_type')->label('Тип чата')->disabled(),
                TextInput::make('first_name')->label('Имя')->disabled(),
                TextInput::make('username')->label('Username')->disabled(),
                TextInput::make('user_id')->label('User ID')->disabled(),
                TextInput::make('chat_id')->label('Chat ID')->disabled(),
                TextInput::make('received_at')->label('Получено')->disabled(),
                TextInput::make('processed_at')->label('Обработано')->disabled(),
                Textarea::make('text')->label('Текст')->disabled()->rows(4)->columnSpanFull(),
                Textarea::make('caption')->label('Подпись (фото)')->disabled()->rows(2)->columnSpanFull(),
            ]);
    }
}
