<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Лог попытки создания карточки Trello по входящему сообщению.
 *
 * Создаётся в TrelloCardCreator после каждой попытки — успешной или нет.
 * При status = 'error' содержит error_message для диагностики.
 * Позволяет отследить, по какому сообщению была создана карточка,
 * и реализовать retry при ошибках.
 */
class TrelloCardLog extends Model
{
    // Явно задаём имя таблицы — Laravel по умолчанию сгенерировал бы trello_card_logs
    protected $table = 'trello_cards_log';

    protected $fillable = [
        'telegram_message_id',
        'trello_card_id',   // null если карточка не была создана
        'trello_card_url',
        'bot_message_id',   // message_id ответа бота с подтверждением создания карточки
        'trello_list_id',   // в какой список пытались создать
        'status',           // success | error
        'error_message',
    ];

    public function telegramMessage(): BelongsTo
    {
        return $this->belongsTo(TelegramMessage::class, 'telegram_message_id');
    }
}
