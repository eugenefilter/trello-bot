<?php

declare(strict_types=1);

namespace Tests\Feature\Webhook;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature-тест webhook endpoint POST /webhooks/telegram.
 *
 * Поднимает полный HTTP-стек Laravel: роуты → middleware → контроллер → БД.
 * Имитирует запросы так, как их отправляет Telegram-сервер.
 * RefreshDatabase сбрасывает БД перед каждым тестом — тесты изолированы друг от друга.
 */
class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-secret-token';

    protected function setUp(): void
    {
        parent::setUp();

        // Прописываем секрет в конфиг перед каждым тестом,
        // чтобы не зависеть от значения в .env.testing.
        config(['telegram.webhook_secret' => $this->secret]);
    }

    /**
     * Базовый happy path: валидный update с правильным токеном → 200.
     * Telegram ожидает именно 200, иначе будет повторять запрос.
     */
    public function test_returns_200_on_valid_request(): void
    {
        $response = $this->postJson(
            '/webhooks/telegram',
            $this->validUpdate(),
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret],
        );

        $response->assertStatus(200);
    }

    /**
     * Проверяет, что все поля из payload корректно сохраняются в telegram_messages.
     * Это критично: если поле потеряется, Job не сможет правильно обработать сообщение.
     */
    public function test_saves_update_to_telegram_messages(): void
    {
        $update = $this->validUpdate();

        $this->postJson(
            '/webhooks/telegram',
            $update,
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret],
        );

        $this->assertDatabaseHas('telegram_messages', [
            'update_id' => $update['update_id'],
            'message_id' => $update['message']['message_id'],
            'chat_id' => $update['message']['chat']['id'],
            'chat_type' => $update['message']['chat']['type'],
            'user_id' => $update['message']['from']['id'],
            'username' => $update['message']['from']['username'],
            'first_name' => $update['message']['from']['first_name'],
            'text' => $update['message']['text'],
        ]);
    }

    /**
     * Запрос с неверным секретным токеном должен быть отклонён с 403.
     * Защищает endpoint от посторонних запросов — только Telegram знает токен.
     */
    public function test_returns_403_on_invalid_secret(): void
    {
        $response = $this->postJson(
            '/webhooks/telegram',
            $this->validUpdate(),
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-token'],
        );

        $response->assertStatus(403);
    }

    /**
     * Idempotency: повторный POST с тем же update_id не должен создавать дубль.
     * Telegram гарантирует at-least-once delivery — один update может прийти дважды
     * при таймаутах или сетевых проблемах.
     */
    public function test_does_not_create_duplicate_on_same_update_id(): void
    {
        $update = $this->validUpdate();
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => $this->secret];

        $this->postJson('/webhooks/telegram', $update, $headers);
        $this->postJson('/webhooks/telegram', $update, $headers);

        $this->assertDatabaseCount('telegram_messages', 1);
    }

    /**
     * Пустое тело запроса (нет update_id) → 400 Bad Request.
     * Защищает от случайных пингов и некорректных вызовов.
     */
    public function test_returns_400_without_body(): void
    {
        $response = $this->postJson(
            '/webhooks/telegram',
            [],
            ['X-Telegram-Bot-Api-Secret-Token' => $this->secret],
        );

        $response->assertStatus(400);
    }

    /**
     * Минимальный валидный Telegram update с текстовым сообщением.
     * Структура соответствует реальному payload от Telegram Bot API.
     */
    private function validUpdate(): array
    {
        return [
            'update_id' => 123456789,
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => 111111,
                    'username' => 'testuser',
                    'first_name' => 'Test',
                ],
                'chat' => [
                    'id' => 222222,
                    'type' => 'private',
                ],
                'date' => 1700000000,
                'text' => 'Hello world',
            ],
        ];
    }
}
