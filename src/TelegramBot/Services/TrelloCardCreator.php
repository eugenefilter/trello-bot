<?php

declare(strict_types=1);

namespace TelegramBot\Services;

use TelegramBot\Contracts\CardLogRepositoryInterface;
use TelegramBot\Contracts\TrelloAdapterInterface;
use TelegramBot\DTOs\CreatedCardResult;
use TelegramBot\DTOs\RoutingResultDTO;
use TelegramBot\DTOs\TelegramMessageDTO;
use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\Exceptions\TrelloAuthException;
use TelegramBot\Exceptions\TrelloConnectionException;
use TelegramBot\Exceptions\TrelloValidationException;
use Throwable;

/**
 * Оркестрирует создание карточки в Trello по входящему сообщению.
 * Ответственность:
 *   1. Собрать TrelloCardDTO из RoutingResultDTO
 *   2. Создать карточку через TrelloAdapterInterface
 *   3. Назначить участников и метки
 *   4. Сохранить результат в trello_cards_log (success или error)
 *   5. Пробросить исключение выше — Job уйдёт в retry
 * Рендеринг шаблонов ({{first_name}}, {{date}} и т.д.) — зона ответственности
 * CardTemplateRenderer (Фаза 5), который подготавливает строки до вызова этого сервиса.
 */
class TrelloCardCreator
{
    public function __construct(
        private readonly TrelloAdapterInterface $trello,
        private readonly CardLogRepositoryInterface $cardLog,
        private readonly TelegramFileDownloader $fileDownloader,
    ) {}

    /**
     * Создаёт карточку Trello и логирует результат.
     *
     * @param  int  $telegramMessageId  ID записи в telegram_messages для связи с логом
     *
     * @throws TrelloAuthException
     * @throws TrelloValidationException
     * @throws TrelloConnectionException|Throwable
     */
    public function create(
        TelegramMessageDTO $message,
        RoutingResultDTO $routing,
        int $telegramMessageId,
    ): CreatedCardResult {
        try {
            $result = $this->trello->createCard(new TrelloCardDTO(
                listId: $routing->listId,
                name: $routing->cardTitleTemplate,
                description: $routing->cardDescriptionTemplate,
                memberIds: $routing->memberIds,
                labelIds: $routing->labelIds,
            ));

            // Назначаем участников и метки отдельными запросами после создания карточки
            $this->trello->addMembersToCard($result->id, $routing->memberIds);
            $this->trello->addLabelsToCard($result->id, $routing->labelIds);

            // Прикрепляем фото к карточке
            foreach ($message->photos as $fileId) {
                $file = $this->fileDownloader->download($fileId, $telegramMessageId);
                $this->trello->attachFile($result->id, $file->localPath, $file->mimeType);
            }

            $this->cardLog->logSuccess(
                telegramMessageId: $telegramMessageId,
                listId: $routing->listId,
                cardId: $result->id,
                cardUrl: $result->url,
            );

            return $result;

        } catch (Throwable $e) {
            $this->cardLog->logError(
                telegramMessageId: $telegramMessageId,
                listId: $routing->listId,
                errorMessage: $e->getMessage(),
            );

            // Пробрасываем исключение — Job должен об этом знать для retry
            throw $e;
        }
    }
}
