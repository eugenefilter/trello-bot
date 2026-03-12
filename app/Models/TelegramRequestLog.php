<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Сырой лог каждого HTTP-запроса на webhook от Telegram.
 * Сохраняется до любой обработки — для отладки.
 */
class TelegramRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payload',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
    ];
}
