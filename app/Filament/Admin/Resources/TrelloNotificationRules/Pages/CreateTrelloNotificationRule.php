<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloNotificationRules\Pages;

use App\Filament\Admin\Resources\TrelloNotificationRules\TrelloNotificationRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloNotificationRule extends CreateRecord
{
    protected static string $resource = TrelloNotificationRuleResource::class;

    public function getTitle(): string
    {
        return 'Новое правило уведомлений';
    }
}
