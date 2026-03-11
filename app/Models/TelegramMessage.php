<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Входящее сообщение от Telegram (один update = одна запись).
 *
 * Хранит как разобранные поля (chat_id, user_id и т.д.) для удобных запросов,
 * так и полный payload_json для воспроизведения при отладке или retry.
 * processed_at проставляется в ProcessTelegramUpdateJob после успешной обработки.
 */
class TelegramMessage extends Model
{
    protected $fillable = [
        'update_id',    // уникальный ID update от Telegram, используется для idempotency
        'message_id',
        'chat_id',
        'chat_type',    // private | group | supergroup | channel
        'user_id',
        'username',
        'first_name',
        'text',
        'caption',      // текст под фото/документом
        'payload_json', // полный сырой update для отладки и retry
        'received_at',  // момент получения webhook
        'processed_at', // момент успешной обработки Job (null = ещё не обработан)
    ];

    protected $casts = [
        'payload_json' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
