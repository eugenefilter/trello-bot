<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrelloMemberBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'trello_member_id',
        'telegram_user_id',
        'telegram_username',
        'telegram_full_name',
    ];

    public function trelloMember(): BelongsTo
    {
        return $this->belongsTo(TrelloMember::class, 'trello_member_id');
    }
}
