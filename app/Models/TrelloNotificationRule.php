<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrelloNotificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'name',
        'is_active',
        'trigger_list_id',
        'filter_label_ids',
        'filter_label_mode',
        'filter_member_binding_ids',
        'telegram_chat_id',
        'mention_member_binding_ids',
        'message_template',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'filter_label_ids' => 'array',
            'filter_member_binding_ids' => 'array',
            'mention_member_binding_ids' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TrelloConnection::class, 'connection_id');
    }

    public function triggerList(): BelongsTo
    {
        return $this->belongsTo(TrelloList::class, 'trigger_list_id', 'trello_list_id');
    }
}
