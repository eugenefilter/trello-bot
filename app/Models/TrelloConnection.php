<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Подключение к Trello-доске.
 *
 * Одно подключение = одна доска. Может быть несколько подключений
 * для разных досок или команд. Все справочники (списки, метки, участники)
 * привязаны к конкретному подключению через connection_id.
 */
class TrelloConnection extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'api_token',
        'board_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lists(): HasMany
    {
        return $this->hasMany(TrelloList::class, 'connection_id');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(TrelloLabel::class, 'connection_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TrelloMember::class, 'connection_id');
    }
}
