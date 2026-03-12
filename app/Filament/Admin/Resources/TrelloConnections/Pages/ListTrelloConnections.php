<?php

namespace App\Filament\Admin\Resources\TrelloConnections\Pages;

use App\Filament\Admin\Resources\TrelloConnections\TrelloConnectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrelloConnections extends ListRecords
{
    protected static string $resource = TrelloConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
