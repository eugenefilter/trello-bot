<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Правило маршрутизации входящего сообщения в список Trello.
 *
 * Порядок проверки условий (от более специфичного к менее):
 *   1. telegram_chat_id + command
 *   2. telegram_chat_id
 *   3. command
 *   4. has_photo
 *   5. default (все условия null — catch-all правило)
 *
 * При нескольких подходящих правилах побеждает наибольший priority.
 * Правила с is_active = false игнорируются RoutingEngine.
 */
class RoutingRule extends Model
{
    protected $fillable = [
        'name',
        'telegram_chat_id',        // null = любой чат
        'chat_type',               // null = любой тип
        'command',                 // null = любая команда
        'keyword',                 // null = без фильтра по ключевому слову
        'has_photo',               // null = не важно, true = только с фото
        'is_forwarded',            // null = не важно, true = только пересланные
        'target_list_id',          // FK → trello_lists.id
        'label_ids',               // JSON-массив Trello label IDs
        'member_ids',              // JSON-массив Trello member IDs
        'card_title_template',
        'card_description_template',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'telegram_chat_id' => 'integer',
        'has_photo' => 'boolean',
        'is_forwarded' => 'boolean',
        'label_ids' => 'array',
        'member_ids' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Список Trello, в который будет создана карточка при срабатывании правила.
     */
    public function targetList(): BelongsTo
    {
        return $this->belongsTo(TrelloList::class, 'target_list_id');
    }
}
