<?php

namespace App\Filament\Admin\Resources\TrelloApiLogs\Pages;

use App\Filament\Admin\Resources\TrelloApiLogs\TrelloApiLogResource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTrelloApiLog extends ViewRecord
{
    protected static string $resource = TrelloApiLogResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('method')->label('Метод')->disabled(),
                TextInput::make('http_status')->label('HTTP статус')->disabled(),
                TextInput::make('endpoint')->label('Endpoint')->disabled()->columnSpanFull(),
                TextInput::make('duration_ms')->label('Время (мс)')->disabled(),
                TextInput::make('created_at')->label('Время')->disabled(),
                Textarea::make('response_body')
                    ->label('Тело ответа (ошибка)')
                    ->disabled()
                    ->rows(15)
                    ->columnSpanFull()
                    ->placeholder('— нет ошибки —'),
            ]);
    }
}
