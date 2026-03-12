<?php

namespace App\Filament\Admin\Resources\TelegramRequestLogs\Pages;

use App\Filament\Admin\Resources\TelegramRequestLogs\TelegramRequestLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramRequestLogs extends ListRecords
{
    protected static string $resource = TelegramRequestLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
