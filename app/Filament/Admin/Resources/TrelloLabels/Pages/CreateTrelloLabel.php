<?php

namespace App\Filament\Admin\Resources\TrelloLabels\Pages;

use App\Filament\Admin\Resources\TrelloLabels\TrelloLabelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloLabel extends CreateRecord
{
    protected static string $resource = TrelloLabelResource::class;
}
