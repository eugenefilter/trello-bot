<?php

declare(strict_types=1);

namespace TelegramBot\Contracts;

use TelegramBot\DTOs\TrelloCardDTO;
use TelegramBot\DTOs\CreatedCardResult;

interface TrelloAdapterInterface
{
    public function createCard(TrelloCardDTO $dto): CreatedCardResult;

    public function attachFile(string $cardId, string $filePath, string $mimeType): void;

    public function addMembersToCard(string $cardId, array $memberIds): void;

    public function addLabelsToCard(string $cardId, array $labelIds): void;

    public function getBoardLists(string $boardId): array;

    public function getBoardLabels(string $boardId): array;

    public function getBoardMembers(string $boardId): array;
}
