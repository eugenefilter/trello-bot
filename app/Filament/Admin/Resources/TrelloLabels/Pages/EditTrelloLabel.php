<?php

namespace App\Filament\Admin\Resources\TrelloLabels\Pages;

use App\Filament\Admin\Resources\TrelloLabels\TrelloLabelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloLabel extends EditRecord
{
    protected static string $resource = TrelloLabelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
