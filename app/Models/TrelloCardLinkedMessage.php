<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrelloCardLinkedMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'chat_id',
        'telegram_message_id',
        'trello_card_id',
        'trello_card_url',
    ];
}
