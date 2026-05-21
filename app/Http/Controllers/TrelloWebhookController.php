<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessTrelloWebhookJob;
use App\Models\TrelloConnection;
use App\Models\TrelloWebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TrelloWebhookController extends Controller
{
    public function verify(TrelloConnection $connection): Response
    {
        return response('OK', 200);
    }

    public function handle(Request $request, TrelloConnection $connection): JsonResponse
    {
        $content = $request->getContent();
        $payload = json_decode($content, true) ?? [];

        TrelloWebhookLog::create([
            'connection_id' => $connection->id,
            'action_type' => $payload['action']['type'] ?? null,
            'payload' => $payload,
        ]);

        if ($connection->is_active) {
            ProcessTrelloWebhookJob::dispatch($connection->id, $content);
        }

        return response()->json(['ok' => true]);
    }
}
