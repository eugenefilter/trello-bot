<?php

namespace App\Filament\Admin\Resources\TrelloConnections\Pages;

use App\Filament\Admin\Resources\TrelloConnections\TrelloConnectionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloConnection extends EditRecord
{
    protected static string $resource = TrelloConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
