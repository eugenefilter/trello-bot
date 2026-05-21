<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TrelloWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle']);

Route::match(['head', 'get'], '/webhooks/trello/{connection:webhook_token}', [TrelloWebhookController::class, 'verify']);
Route::post('/webhooks/trello/{connection:webhook_token}', [TrelloWebhookController::class, 'handle']);
