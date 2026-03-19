<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use Illuminate\Support\Facades\Log;
use TelegramBot\CallbackHandlers\CallbackActionHandlerInterface;
use TelegramBot\DTOs\CallbackAction;
use TelegramBot\DTOs\TelegramCallbackDTO;

/**
 * Роутер callback_query: парсит action и делегирует зарегистрированному хэндлеру.
 *
 * Хэндлеры регистрируются через конструктор в виде map ['action' => handler].
 * Добавление нового действия = новый класс-хэндлер + одна строка в AppServiceProvider.
 */
class CallbackQueryProcessor
{
    /**
     * @param  array<string, CallbackActionHandlerInterface>  $handlers
     */
    public function __construct(
        private readonly array $handlers,
    ) {}

    public function process(TelegramCallbackDTO $dto): void
    {
        $action = CallbackAction::fromData($dto->data);

        if ($action === null) {
            Log::warning('CallbackQueryProcessor: invalid callback_data format', ['data' => $dto->data]);

            return;
        }

        $handler = $this->handlers[$action->action] ?? null;

        if ($handler === null) {
            Log::warning('CallbackQueryProcessor: unknown action', [
                'action' => $action->action,
                'callback_id' => $dto->callbackId,
            ]);

            return;
        }

        $handler->handle($dto, $action->payload);
    }
}
