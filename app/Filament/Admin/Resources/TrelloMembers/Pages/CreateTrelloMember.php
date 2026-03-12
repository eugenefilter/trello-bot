<?php

namespace App\Filament\Admin\Resources\TrelloMembers\Pages;

use App\Filament\Admin\Resources\TrelloMembers\TrelloMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrelloMember extends CreateRecord
{
    protected static string $resource = TrelloMemberResource::class;
}
