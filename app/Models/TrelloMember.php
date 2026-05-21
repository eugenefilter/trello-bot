<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Участник (member) Trello-доски.
 *
 * Заполняется через SyncTrelloBoardCommand.
 * is_active = false означает, что участник покинул доску
 * при последней синхронизации.
 */
class TrelloMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'trello_member_id', // ID участника в Trello API
        'username',         // @username в Trello
        'full_name',        // отображаемое имя
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TrelloConnection::class, 'connection_id');
    }

    public function binding(): HasOne
    {
        return $this->hasOne(TrelloMemberBinding::class, 'trello_member_id');
    }
}
