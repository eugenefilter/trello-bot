<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Валидирует входящий webhook-запрос от Telegram.
 *
 * authorize() — проверяет секретный токен из заголовка X-Telegram-Bot-Api-Secret-Token.
 * rules()     — проверяет обязательные поля payload.
 */
class TelegramWebhookRequest extends FormRequest
{
    /**
     * Проверяет подпись запроса — секретный токен, заданный при регистрации webhook.
     * Возвращает false → 403 Forbidden (через failedAuthorization).
     */
    public function authorize(): bool
    {
        return $this->header('X-Telegram-Bot-Api-Secret-Token') === config('telegram.webhook_secret');
    }

    /**
     * update_id обязателен в любом валидном Telegram update.
     */
    public function rules(): array
    {
        return [
            'update_id' => ['required', 'integer'],
        ];
    }

    /**
     * Возвращает 403 вместо стандартного 401, чтобы не раскрывать наличие endpoint.
     */
    protected function failedAuthorization(): never
    {
        throw new HttpResponseException(
            response()->json(['error' => 'Forbidden'], 403)
        );
    }

    /**
     * Возвращает 400 вместо стандартного 422 — соответствует ожиданиям Telegram.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['error' => 'Bad Request'], 400)
        );
    }
}
