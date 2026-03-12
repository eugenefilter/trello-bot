<?php

namespace App\Filament\Admin\Resources\TelegramMessages\Pages;

use App\Filament\Admin\Resources\TelegramMessages\TelegramMessageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramMessage extends ViewRecord
{
    protected static string $resource = TelegramMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
