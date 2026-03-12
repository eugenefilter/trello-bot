<?php

namespace App\Filament\Admin\Resources\TrelloCardLogs\Pages;

use App\Filament\Admin\Resources\TrelloCardLogs\TrelloCardLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloCardLog extends EditRecord
{
    protected static string $resource = TrelloCardLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
