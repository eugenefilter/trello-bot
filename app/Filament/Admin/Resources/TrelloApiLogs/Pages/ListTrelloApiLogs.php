<?php

namespace App\Filament\Admin\Resources\TrelloApiLogs\Pages;

use App\Filament\Admin\Resources\TrelloApiLogs\TrelloApiLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTrelloApiLogs extends ListRecords
{
    protected static string $resource = TrelloApiLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
