<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdateJob;
use App\Models\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Принимает входящие webhook-запросы от Telegram.
 * Контроллер намеренно минимален: только проверка подписи,
 * сохранение сырого update и возврат 200. Вся бизнес-логика
 * обрабатывается асинхронно в ProcessTelegramUpdateJob (Фаза 4).
 */
final class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Проверяем секретный токен, который Telegram отправляет в заголовке.
        // Значение задаётся при регистрации webhook через setWebhook API.
        if ($request->header('X-Telegram-Bot-Api-Secret-Token') !== config('telegram.webhook_secret')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $payload = $request->all();

        // update_id обязателен в любом валидном Telegram update.
        if (empty($payload) || ! isset($payload['update_id'])) {
            return response()->json(['error' => 'Bad Request'], 400);
        }

        $message = $payload['message'] ?? [];

        // firstOrCreate обеспечивает идемпотентность:
        // повторный POST с тем же update_id не создаст дубль.
        $telegramMessage = TelegramMessage::query()->firstOrCreate(
            ['update_id' => $payload['update_id']],
            [
                'message_id' => $message['message_id'] ?? null,
                'chat_id' => $message['chat']['id'] ?? 0,
                'chat_type' => $message['chat']['type'] ?? 'unknown',
                'user_id' => $message['from']['id'] ?? null,
                'username' => $message['from']['username'] ?? null,
                'first_name' => $message['from']['first_name'] ?? null,
                'text' => $message['text'] ?? null,
                'caption' => $message['caption'] ?? null,
                'payload_json' => $payload,
                'received_at' => now(),
            ],
        );

        // Запускаем обработку асинхронно — контроллер сразу возвращает 200 Telegram'у.
        // При повторном update_id (idempotent request) wasRecentlyCreated = false,
        // и мы не диспатчим Job дважды.
        if ($telegramMessage->wasRecentlyCreated) {
            ProcessTelegramUpdateJob::dispatch($telegramMessage->id);
        }

        return response()->json(['ok' => true]);
    }
}
