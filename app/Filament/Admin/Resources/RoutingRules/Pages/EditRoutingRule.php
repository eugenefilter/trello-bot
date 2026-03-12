<?php

namespace App\Filament\Admin\Resources\RoutingRules\Pages;

use App\Filament\Admin\Resources\RoutingRules\RoutingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoutingRule extends EditRecord
{
    protected static string $resource = RoutingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
