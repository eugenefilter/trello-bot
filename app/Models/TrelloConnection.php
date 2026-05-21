<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Подключение к Trello-доске.
 *
 * Одно подключение = одна доска. Может быть несколько подключений
 * для разных досок или команд. Все справочники (списки, метки, участники)
 * привязаны к конкретному подключению через connection_id.
 */
class TrelloConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_key',
        'api_token',
        'board_id',
        'is_active',
        'webhook_token',
        'webhook_id',
        'webhook_registered_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $connection): void {
            $connection->webhook_token ??= Str::uuid()->toString();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'webhook_registered_at' => 'datetime',
        ];
    }

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
