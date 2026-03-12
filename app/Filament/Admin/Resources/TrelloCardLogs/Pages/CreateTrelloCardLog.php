<?php

namespace App\Filament\Admin\Resources\TrelloCardLogs\Pages;

use App\Filament\Admin\Resources\TrelloCardLogs\TrelloCardLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloCardLog extends CreateRecord
{
    protected static string $resource = TrelloCardLogResource::class;
}
