<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/telegram', [TelegramWebhookController::class, 'handle']);
