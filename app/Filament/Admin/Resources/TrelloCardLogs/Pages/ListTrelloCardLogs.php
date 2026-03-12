<?php

namespace App\Filament\Admin\Resources\TrelloCardLogs\Pages;

use App\Filament\Admin\Resources\TrelloCardLogs\TrelloCardLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrelloCardLogs extends ListRecords
{
    protected static string $resource = TrelloCardLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
