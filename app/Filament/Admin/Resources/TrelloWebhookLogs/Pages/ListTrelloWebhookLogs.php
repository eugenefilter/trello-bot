<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloWebhookLogs\Pages;

use App\Filament\Admin\Resources\TrelloWebhookLogs\TrelloWebhookLogResource;
use Filament\Resources\Pages\ListRecords;

class ListTrelloWebhookLogs extends ListRecords
{
    protected static string $resource = TrelloWebhookLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
