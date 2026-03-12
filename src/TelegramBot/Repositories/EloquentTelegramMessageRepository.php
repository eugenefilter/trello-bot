<?php

declare(strict_types=1);

namespace TelegramBot\Repositories;

use App\Models\TelegramMessage;
use TelegramBot\Contracts\TelegramMessageRepositoryInterface;

/**
 * Eloquent-реализация репозитория входящих Telegram update.
 */
class EloquentTelegramMessageRepository implements TelegramMessageRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function firstOrCreate(array $payload): array
    {
        $message = $payload['message'] ?? [];

        $model = TelegramMessage::query()->firstOrCreate(
            ['update_id' => $payload['update_id']],
            [
                'message_id'   => $message['message_id'] ?? null,
                'chat_id'      => $message['chat']['id'] ?? 0,
                'chat_type'    => $message['chat']['type'] ?? 'unknown',
                'user_id'      => $message['from']['id'] ?? null,
                'username'     => $message['from']['username'] ?? null,
                'first_name'   => $message['from']['first_name'] ?? null,
                'text'         => $message['text'] ?? null,
                'caption'      => $message['caption'] ?? null,
                'payload_json' => $payload,
                'received_at'  => now(),
            ],
        );

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
        TelegramMessage::query()->whereKey($id)->update(['processed_at' => now()]);
    }
}
