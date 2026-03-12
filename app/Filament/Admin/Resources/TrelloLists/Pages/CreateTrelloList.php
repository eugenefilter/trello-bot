<?php

namespace App\Filament\Admin\Resources\TrelloLists\Pages;

use App\Filament\Admin\Resources\TrelloLists\TrelloListResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloList extends CreateRecord
{
    protected static string $resource = TrelloListResource::class;
}
