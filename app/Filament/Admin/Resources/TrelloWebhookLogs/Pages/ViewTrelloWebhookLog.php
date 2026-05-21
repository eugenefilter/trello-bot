<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloWebhookLogs\Pages;

use App\Filament\Admin\Resources\TrelloWebhookLogs\TrelloWebhookLogResource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTrelloWebhookLog extends ViewRecord
{
    protected static string $resource = TrelloWebhookLogResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('connection.name')->label('Подключение')->disabled(),
                TextInput::make('action_type')->label('Тип события')->disabled(),
                TextInput::make('received_at')->label('Получен')->disabled(),
                Textarea::make('payload')
                    ->label('Payload (JSON)')
                    ->disabled()
                    ->rows(20)
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        : $state
                    )
                    ->columnSpanFull(),
            ]);
    }
}
