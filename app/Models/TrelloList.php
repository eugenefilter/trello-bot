<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Список (колонка) Trello-доски.
 *
 * Заполняется через SyncTrelloBoardCommand (php artisan trello:sync).
 * is_active = false означает, что список был удалён на стороне Trello
 * при последней синхронизации.
 */
class TrelloList extends Model
{
    protected $fillable = [
        'connection_id',
        'trello_list_id', // ID списка в Trello API
        'board_id',
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TrelloConnection::class, 'connection_id');
    }
}
