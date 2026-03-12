<?php

namespace App\Filament\Admin\Resources\TrelloConnections\Pages;

use App\Filament\Admin\Resources\TrelloConnections\TrelloConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloConnection extends CreateRecord
{
    protected static string $resource = TrelloConnectionResource::class;
}
