<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Лог каждого HTTP-запроса к Trello API.
 * Создаётся в TrelloAdapter после каждого вызова — успешного или нет.
 */
class TrelloApiLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'method',
        'endpoint',
        'http_status',
        'response_body',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
