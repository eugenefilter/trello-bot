<?php

namespace App\Filament\Admin\Resources\TrelloMembers\Pages;

use App\Filament\Admin\Resources\TrelloMembers\TrelloMemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloMember extends EditRecord
{
    protected static string $resource = TrelloMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
