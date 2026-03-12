<?php

namespace App\Filament\Admin\Resources\TrelloLists\Pages;

use App\Filament\Admin\Resources\TrelloLists\TrelloListResource;

use Filament\Resources\Pages\ListRecords;

class ListTrelloLists extends ListRecords
{
    protected static string $resource = TrelloListResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
