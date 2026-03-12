<?php

namespace App\Filament\Admin\Resources\TelegramRequestLogs\Pages;

use App\Filament\Admin\Resources\TelegramRequestLogs\TelegramRequestLogResource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTelegramRequestLog extends ViewRecord
{
    protected static string $resource = TelegramRequestLogResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('id')->label('#')->disabled(),
                TextInput::make('received_at')->label('Получено')->disabled(),
                Textarea::make('payload')
                    ->label('JSON payload')
                    ->disabled()
                    ->rows(40)
                    ->columnSpanFull()
                    ->formatStateUsing(
                        fn (mixed $state) => is_array($state)
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : $state
                    ),
            ]);
    }
}
