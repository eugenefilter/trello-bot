<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use TelegramBot\Contracts\RoutingEngineInterface;
use TelegramBot\Contracts\TelegramAdapterInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;
use TelegramBot\Contracts\UpdateParserInterface;
use TelegramBot\DTOs\RoutingResultDTO;

/**
 * Оркестрирует обработку одного Telegram update.
 *
 * Порядок:
 *   1. Загружает payload из репозитория
 *   2. Парсит payload → TelegramMessageDTO (null = неподдерживаемый тип)
 *   3. Ищет routing rule → RoutingResultDTO (null = нет подходящего правила)
 *   4. Рендерит шаблоны карточки через CardTemplateRenderer
 *   5. Создаёт карточку в Trello
 *   6. Отправляет подтверждение пользователю через Telegram
 *   7. Помечает сообщение как обработанное
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
        private readonly CardTemplateRenderer $renderer,
        private readonly TelegramAdapterInterface $telegram,
    ) {}

    /**
     * @throws \Throwable при ошибке Trello — Job уйдёт в retry
     */
    public function process(int $telegramMessageId): void
    {
        $payload = $this->repository->getPayload($telegramMessageId);

        $dto = $this->parser->parse($payload);

        if ($dto === null) {
            $this->repository->markSkipped($telegramMessageId, 'Неподдерживаемый тип сообщения');

            return;
        }

        $routingResult = $this->routing->resolve($dto);

        if ($routingResult === null) {
            $this->repository->markSkipped($telegramMessageId, 'Правило маршрутизации не найдено');

            return;
        }

        $rendered = new RoutingResultDTO(
            listId: $routingResult->listId,
            listName: $routingResult->listName,
            memberIds: $routingResult->memberIds,
            labelIds: $routingResult->labelIds,
            cardTitleTemplate: $this->renderer->render($routingResult->cardTitleTemplate, $dto),
            cardDescriptionTemplate: $this->renderer->render($routingResult->cardDescriptionTemplate, $dto),
        );

        $result = $this->cardCreator->create($dto, $rendered, $telegramMessageId);

        $this->telegram->sendMessage(
            $dto->chatId,
            $this->buildReplyText($rendered->listName, $result->url),
        );

        $this->repository->markProcessed($telegramMessageId);
    }

    private function buildReplyText(string $listName, string $cardUrl): string
    {
        return "✅ Карточка создана\n\nКолонка: {$listName}\nСсылка: {$cardUrl}";
    }
}
