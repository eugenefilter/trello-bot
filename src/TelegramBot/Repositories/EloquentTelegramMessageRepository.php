<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TelegramFile;
use App\Models\TelegramMessage;
use App\Models\TrelloCardLog;
use TelegramBot\Contracts\TelegramFileRepositoryInterface;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;

/**
 * Eloquent-реализация репозитория входящих Telegram update.
 */
class EloquentTelegramMessageRepository implements TelegramMessageRepositoryInterface
{
    public function __construct(
        private readonly TelegramFileRepositoryInterface $fileRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function firstOrCreate(array $payload): array
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? [];

        $model = TelegramMessage::query()->firstOrCreate(
            ['update_id' => $payload['update_id']],
            [
                'message_id' => $message['message_id'] ?? null,
                'chat_id' => $message['chat']['id'] ?? 0,
                'chat_type' => $message['chat']['type'] ?? 'unknown',
                'user_id' => $message['from']['id'] ?? null,
                'username' => $message['from']['username'] ?? null,
                'first_name' => $message['from']['first_name'] ?? null,
                'media_group_id' => $message['media_group_id'] ?? null,
                'text' => $message['text'] ?? null,
                'caption' => $message['caption'] ?? null,
                'payload_json' => $payload,
                'received_at' => now(),
            ],
        );

        if ($model->wasRecentlyCreated) {
            $this->saveFiles($model->id, $message);
        }

        return ['id' => $model->id, 'created' => $model->wasRecentlyCreated];
    }

    /**
     * {@inheritDoc}
     */
    public function getPayload(int $id): array
    {
        /** @var TelegramMessage $model */
        $model = TelegramMessage::query()->findOrFail($id);

        return $model->payload_json;
    }

    /**
     * {@inheritDoc}
     */
    public function markProcessed(int $id): void
    {
        TelegramMessage::query()->whereKey($id)->update([
            'processed_at' => now(),
            'processing_status' => 'success',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function markSkipped(int $id, string $reason): void
    {
        TelegramMessage::query()->whereKey($id)->update([
            'processed_at' => now(),
            'processing_status' => 'skipped',
            'processing_notes' => $reason,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function markFailed(int $id, string $reason): void
    {
        TelegramMessage::query()->whereKey($id)->update([
            'processing_status' => 'failed',
            'processing_notes' => $reason,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function findCardIdByMediaGroup(string $mediaGroupId): ?string
    {
        return TrelloCardLog::query()
            ->join('telegram_messages', 'telegram_messages.id', '=', 'trello_cards_log.telegram_message_id')
            ->where('telegram_messages.media_group_id', $mediaGroupId)
            ->whereNotNull('trello_cards_log.trello_card_id')
            ->where('trello_cards_log.status', 'success')
            ->value('trello_cards_log.trello_card_id');
    }

    /**
     * {@inheritDoc}
     */
    public function findSkippedGroupParts(string $mediaGroupId, int $excludeMessageId): array
    {
        return TelegramMessage::query()
            ->where('media_group_id', $mediaGroupId)
            ->where('processing_status', 'skipped')
            ->where('id', '!=', $excludeMessageId)
            ->get()
            ->map(function (TelegramMessage $message) {
                return [
                    'id' => $message->id,
                    'file_ids' => TelegramFile::query()
                        ->where('telegram_message_id', $message->id)
                        ->pluck('file_id')
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function findOriginalCardByMessage(string $chatId, int $messageId): ?array
    {
        $result = TrelloCardLog::query()
            ->select('trello_cards_log.trello_card_id', 'trello_cards_log.trello_card_url', 'trello_cards_log.telegram_message_id')
            ->join('telegram_messages', 'telegram_messages.id', '=', 'trello_cards_log.telegram_message_id')
            ->where('telegram_messages.chat_id', $chatId)
            ->where('telegram_messages.message_id', $messageId)
            ->where('trello_cards_log.status', 'success')
            ->whereNotNull('trello_cards_log.trello_card_id')
            ->first();

        if ($result === null) {
            return null;
        }

        return [
            'telegram_message_id' => $result->telegram_message_id,
            'card_id' => $result->trello_card_id,
            'card_url' => $result->trello_card_url,
        ];
    }

    /**
     * Сохраняет фото/документы из payload в telegram_files.
     * Вызывается только для новых (не дублирующих) update.
     * Сохраняет также файлы из reply_to_message для отслеживания при последующих редактированиях.
     */
    private function saveFiles(int $messageId, array $message): void
    {
        $photoSizes = $message['photo'] ?? [];

        if (! empty($photoSizes)) {
            // Берём последний элемент — максимальное разрешение (как в парсере)
            $largest = end($photoSizes);
            $this->fileRepository->createForMessage($messageId, $largest, 'photo');
        }

        $document = $message['document'] ?? null;

        if ($document !== null) {
            $this->fileRepository->createForMessage($messageId, $document, 'document');
        }

        $reply = $message['reply_to_message'] ?? null;

        if ($reply !== null) {
            $replyPhotos = $reply['photo'] ?? [];

            if (! empty($replyPhotos)) {
                $largest = end($replyPhotos);
                $this->fileRepository->createForMessage($messageId, $largest, 'photo');
            }

            $replyDocument = $reply['document'] ?? null;

            if ($replyDocument !== null) {
                $this->fileRepository->createForMessage($messageId, $replyDocument, 'document');
            }
        }
    }
}
