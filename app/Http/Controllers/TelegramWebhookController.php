<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TelegramWebhookRequest;
use App\Jobs\ProcessTelegramUpdateJob;
use Illuminate\Http\JsonResponse;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;

/**
 * Принимает входящие webhook-запросы от Telegram.
 *
 * Контроллер намеренно минимален: валидация — в TelegramWebhookRequest,
 * сохранение — в TelegramMessageRepositoryInterface, обработка — в Job.
 */
final class TelegramWebhookController extends Controller
{
    public function handle(
        TelegramWebhookRequest $request,
        TelegramMessageRepositoryInterface $repository,
    ): JsonResponse {
        $payload = $request->all();

        // Обрабатываем только обычные сообщения (message).
        // inline_query, edited_message, channel_post и прочие типы игнорируем —
        // Telegram ожидает 200, чтобы не повторять запрос.
        if (! isset($payload['message'])) {
            return response()->json(['ok' => true]);
        }

        ['id' => $id, 'created' => $created] = $repository->firstOrCreate($payload);

        // Диспатчим Job только для новых update — повторный webhook не создаёт дубль.
        if ($created) {
            ProcessTelegramUpdateJob::dispatch($id);
        }

        return response()->json(['ok' => true]);
    }
}
