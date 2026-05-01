<?php

declare(strict_types=1);

namespace TelegramBot\CallbackHandlers;

use TelegramBot\Contracts\RoutingRuleRepositoryInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\DTOs\TelegramCallbackDTO;
use TelegramBot\Services\TelegramUpdateProcessor;

/**
 * Обрабатывает действие "create_card:{messageId}_{ruleId}".
 *
 * Вызывается когда пользователь выбирает правило из picker-а пересланного сообщения.
 * Загружает оригинальное сообщение из БД и создаёт карточку через выбранное правило.
 */
class CreateCardHandler implements CallbackActionHandlerInterface
{
    public function __construct(
        private readonly TelegramAdapterInterface $telegram,
        private readonly TelegramUpdateProcessor $processor,
        private readonly RoutingRuleRepositoryInterface $ruleRepository,
    ) {}

    public function handle(TelegramCallbackDTO $dto, string $payload): void
    {
        $parts = explode('_', $payload, 2);

        if (count($parts) !== 2) {
            $this->telegram->answerCallbackQuery($dto->callbackId, '❌');

            return;
        }

        $rule = $this->ruleRepository->getRuleById((int) $parts[1]);

        if ($rule === null) {
            $this->telegram->answerCallbackQuery($dto->callbackId, '❌');

            return;
        }

        $this->telegram->answerCallbackQuery($dto->callbackId, '⏳');
        $this->telegram->removeInlineKeyboard($dto->chatId, $dto->messageId);

        $this->processor->processWithRule((int) $parts[0], $rule);
    }
}
