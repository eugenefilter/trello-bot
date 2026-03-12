<?php

namespace App\Filament\Admin\Resources\TrelloApiLogs\Pages;

use App\Filament\Admin\Resources\TrelloApiLogs\TrelloApiLogResource;
use App\Models\AppSetting;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListTrelloApiLogs extends ListRecords
{
    protected static string $resource = TrelloApiLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_logging')
                ->label(fn () => AppSetting::getBool('trello_api_logging')
                    ? 'Логирование включено'
                    : 'Логирование выключено')
                ->color(fn () => AppSetting::getBool('trello_api_logging')
                    ? 'success'
                    : 'danger')
                ->icon(fn () => AppSetting::getBool('trello_api_logging')
                    ? 'heroicon-o-eye'
                    : 'heroicon-o-eye-slash')
                ->action(function () {
                    AppSetting::setBool('trello_api_logging', ! AppSetting::getBool('trello_api_logging'));
                }),
        ];
    }
}
