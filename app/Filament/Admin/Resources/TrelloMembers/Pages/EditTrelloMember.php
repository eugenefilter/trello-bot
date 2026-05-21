<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\TrelloMembers\Pages;

use App\Filament\Admin\Resources\TrelloMembers\TrelloMemberResource;
use Filament\Resources\Pages\EditRecord;

class EditTrelloMember extends EditRecord
{
    protected static string $resource = TrelloMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $binding = $this->record->binding;

        $data['telegram_user_id'] = $binding?->telegram_user_id;
        $data['telegram_username'] = $binding?->telegram_username;
        $data['telegram_full_name'] = $binding?->telegram_full_name;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $username = $data['telegram_username'] ?? null;

        $this->record->binding()->updateOrCreate([], [
            'telegram_user_id' => $data['telegram_user_id'] ?? null,
            'telegram_username' => $username !== null ? ltrim($username, '@') : null,
            'telegram_full_name' => $data['telegram_full_name'] ?? null,
        ]);

        unset($data['telegram_user_id'], $data['telegram_username'], $data['telegram_full_name']);

        return $data;
    }
}
