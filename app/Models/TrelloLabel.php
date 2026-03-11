<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Метка (label) Trello-доски.
 *
 * Заполняется через SyncTrelloBoardCommand.
 * name может быть null — Trello позволяет метки только с цветом без названия.
 */
class TrelloLabel extends Model
{
    protected $fillable = [
        'connection_id',
        'trello_label_id', // ID метки в Trello API
        'board_id',
        'name',
        'color',           // green | yellow | red | purple | blue | sky | lime | pink | black
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
