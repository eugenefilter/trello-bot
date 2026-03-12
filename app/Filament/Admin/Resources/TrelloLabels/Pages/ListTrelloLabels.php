<?php

namespace App\Filament\Admin\Resources\TrelloLabels\Pages;

use App\Filament\Admin\Resources\TrelloLabels\TrelloLabelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrelloLabels extends ListRecords
{
    protected static string $resource = TrelloLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
