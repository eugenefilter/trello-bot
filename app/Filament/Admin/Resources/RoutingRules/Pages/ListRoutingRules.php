<?php

namespace App\Filament\Admin\Resources\RoutingRules\Pages;

use App\Filament\Admin\Resources\RoutingRules\RoutingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoutingRules extends ListRecords
{
    protected static string $resource = RoutingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
