<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloNotificationRules\Pages;

use App\Filament\Admin\Resources\TrelloNotificationRules\TrelloNotificationRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrelloNotificationRule extends EditRecord
{
    protected static string $resource = TrelloNotificationRuleResource::class;

    public function getTitle(): string
    {
        return "Правило: {$this->record->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
