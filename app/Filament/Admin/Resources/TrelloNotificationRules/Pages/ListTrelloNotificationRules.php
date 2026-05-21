<?php

namespace App\Filament\Admin\Resources\TrelloNotificationRules\Pages;

use App\Filament\Admin\Resources\TrelloNotificationRules\TrelloNotificationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrelloNotificationRules extends ListRecords
{
    protected static string $resource = TrelloNotificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
