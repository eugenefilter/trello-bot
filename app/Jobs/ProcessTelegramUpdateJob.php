<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TelegramMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\Services\TrelloCardCreator;

/**
 * Обрабатывает один Telegram update асинхронно.
 *
 * Порядок выполнения:
 *   1. Загружает TelegramMessage из БД по ID
 *   2. Парсит payload_json → TelegramMessageDTO
 *   3. Ищет подходящее routing rule → RoutingResultDTO
 *   4. Создаёт карточку в Trello через TrelloCardCreator
 *   5. Ставит processed_at на TelegramMessage
 *
 * При ошибке Trello (исключение из TrelloCardCreator) processed_at НЕ ставится —
 * Laravel Queue повторит Job согласно политике retry.
 */
class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Queueable;

    /**
     * Максимальное количество попыток выполнения Job при ошибках Trello.
     */
    public int $tries = 3;

    public function __construct(
        private readonly int $telegramMessageId,
    ) {}

    /**
     * Зависимости инжектируются Laravel-контейнером через метод handle().
     * Это позволяет легко мокировать их в тестах без боллер-плейта.
     */
    public function handle(
        UpdateParserInterface $parser,
        RoutingEngineInterface $routing,
        TrelloCardCreator $cardCreator,
    ): void {
        $telegramMessage = TelegramMessage::query()->findOrFail($this->telegramMessageId);

        // --- Шаг 1: Распарсить сырой payload в DTO ---
        $dto = $parser->parse($telegramMessage->payload_json);

        if ($dto === null) {
            // Update не содержит поддерживаемого типа сообщения (channel_post, edited_message и т.д.)
            $telegramMessage->update(['processed_at' => now()]);
            return;
        }

        // --- Шаг 2: Найти routing rule ---
        $routingResult = $routing->resolve($dto);

        if ($routingResult === null) {
            // Ни одно активное правило не совпало — сообщение игнорируется
            $telegramMessage->update(['processed_at' => now()]);
            return;
        }

        // --- Шаг 3: Создать карточку ---
        // Исключения из cardCreator не перехватываем — Job уйдёт в retry.
        // TrelloCardCreator сам залогирует ошибку в trello_cards_log перед пробросом.
        $cardCreator->create($dto, $routingResult, $this->telegramMessageId);

        // --- Шаг 4: Пометить как обработанное ---
        $telegramMessage->update(['processed_at' => now()]);
    }
}
