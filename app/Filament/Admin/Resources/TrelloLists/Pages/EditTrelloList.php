<?php

namespace App\Filament\Admin\Resources\TrelloLists\Pages;

use App\Filament\Admin\Resources\TrelloLists\TrelloListResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloList extends EditRecord
{
    protected static string $resource = TrelloListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
