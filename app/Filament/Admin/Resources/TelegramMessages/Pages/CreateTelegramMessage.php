<?php

namespace App\Filament\Admin\Resources\TelegramMessages\Pages;

use App\Filament\Admin\Resources\TelegramMessages\TelegramMessageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelegramMessage extends CreateRecord
{
    protected static string $resource = TelegramMessageResource::class;
}
