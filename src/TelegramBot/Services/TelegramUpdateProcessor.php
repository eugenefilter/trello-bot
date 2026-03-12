<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\UpdateParserInterface;

/**
 * Оркестрирует обработку одного Telegram update.
 *
 * Порядок:
 *   1. Загружает payload из репозитория
 *   2. Парсит payload → TelegramMessageDTO (null = неподдерживаемый тип)
 *   3. Ищет routing rule → RoutingResultDTO (null = нет подходящего правила)
 *   4. Создаёт карточку в Trello
 *   5. Помечает сообщение как обработанное
 *
 * При исключении из TrelloCardCreator markProcessed не вызывается —
 * Job повторит обработку согласно политике retry.
 */
class TelegramUpdateProcessor
{
    public function __construct(
        private readonly TelegramMessageRepositoryInterface $repository,
        private readonly UpdateParserInterface $parser,
        private readonly RoutingEngineInterface $routing,
        private readonly TrelloCardCreator $cardCreator,
    ) {}

    /**
     * @throws \Throwable при ошибке Trello — Job уйдёт в retry
     */
    public function process(int $telegramMessageId): void
    {
        $payload = $this->repository->getPayload($telegramMessageId);

        $dto = $this->parser->parse($payload);

        if ($dto === null) {
            $this->repository->markProcessed($telegramMessageId);
            return;
        }

        $routingResult = $this->routing->resolve($dto);

        if ($routingResult === null) {
            $this->repository->markProcessed($telegramMessageId);
            return;
        }

        $this->cardCreator->create($dto, $routingResult, $telegramMessageId);

        $this->repository->markProcessed($telegramMessageId);
    }
}
