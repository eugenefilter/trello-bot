<?php

namespace App\Filament\Admin\Resources\TrelloMembers\Pages;

use App\Filament\Admin\Resources\TrelloMembers\TrelloMemberResource;
use Filament\Resources\Pages\ListRecords;

class ListTrelloMembers extends ListRecords
{
    protected static string $resource = TrelloMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
