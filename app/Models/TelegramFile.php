<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Файл (фото или документ), прикреплённый к входящему сообщению.
 *
 * Жизненный цикл local_path:
 *   null → файл ещё не скачан
 *   путь → TelegramFileDownloader скачал файл на сервер
 *   null → файл удалён после успешной загрузки в Trello
 */
class TelegramFile extends Model
{
    protected $fillable = [
        'telegram_message_id',
        'file_id',         // ID файла в Telegram (используется для вызова getFile)
        'file_unique_id',  // стабильный ID, не меняется при переотправке того же файла
        'file_path',       // относительный путь для скачивания через Telegram API
        'file_type',       // photo | document
        'local_path',      // абсолютный путь после скачивания на сервер (null если не скачан)
        'mime_type',
        'size',            // размер в байтах
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TelegramMessage::class, 'telegram_message_id');
    }
}
