<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TrelloConnection;
use App\Models\TrelloMember;
use App\Models\TrelloMemberBinding;
use App\Models\TrelloNotificationRule;
use Illuminate\Http\Client\Factory as HttpFactory;
use TelegramBot\Contracts\TelegramAdapterInterface;

class TrelloNotificationProcessor
{
    private const string TRELLO_API = 'https://api.trello.com/1';

    public function __construct(
        private readonly HttpFactory $http,
        private readonly TelegramAdapterInterface $telegram,
    ) {}

    public function process(TrelloConnection $connection, array $payload): void
    {
        $action = $payload['action'] ?? null;

        if (! $action || ($action['type'] ?? '') !== 'updateCard') {
            return;
        }

        $data = $action['data'] ?? [];
        $listAfter = $data['listAfter'] ?? null;

        if (! $listAfter) {
            return;
        }

        $card = $data['card'] ?? [];
        $cardId = $card['id'] ?? null;
        $cardName = $card['name'] ?? '';
        $cardShortLink = $card['shortLink'] ?? null;
        $listName = $listAfter['name'] ?? '';
        $listId = $listAfter['id'] ?? null;
        $boardName = $data['board']['name'] ?? ($payload['model']['name'] ?? '');

        $rules = TrelloNotificationRule::query()
            ->where('connection_id', $connection->id)
            ->where('is_active', true)
            ->where('trigger_list_id', $listId)
            ->get();

        if ($rules->isEmpty()) {
            return;
        }

        $needsDetails = $rules->contains(
            fn (TrelloNotificationRule $r) => $r->filter_label_ids !== null || $r->filter_member_binding_ids !== null
        );

        $cardDetails = $needsDetails && $cardId
            ? $this->fetchCardDetails($connection, $cardId)
            : null;

        $cardUrl = $cardShortLink ? "https://trello.com/c/{$cardShortLink}" : '';

        foreach ($rules as $rule) {
            if (! $this->matchesRule($rule, $connection, $cardDetails)) {
                continue;
            }

            $mentions = $this->buildMentions($rule);

            $message = str_replace(
                ['{{card_name}}', '{{card_url}}', '{{list_name}}', '{{board_name}}', '{{mentions}}'],
                [$cardName, $cardUrl, $listName, $boardName, $mentions],
                $rule->message_template,
            );

            $this->telegram->sendMessage((string) $rule->telegram_chat_id, $message);
        }
    }

    private function fetchCardDetails(TrelloConnection $connection, string $cardId): ?array
    {
        $response = $this->http
            ->withQueryParameters([
                'key' => $connection->api_key,
                'token' => $connection->api_token,
                'fields' => 'idLabels,idMembers',
            ])
            ->get(self::TRELLO_API."/cards/{$cardId}");

        return $response->ok() ? $response->json() : null;
    }

    private function matchesRule(TrelloNotificationRule $rule, TrelloConnection $connection, ?array $cardDetails): bool
    {
        if ($rule->filter_label_ids !== null) {
            $cardLabels = $cardDetails['idLabels'] ?? [];
            $filterLabels = $rule->filter_label_ids;
            $mode = $rule->filter_label_mode ?? 'any';

            if ($mode === 'all') {
                if (! empty(array_diff($filterLabels, $cardLabels))) {
                    return false;
                }
            } else {
                if (empty(array_intersect($filterLabels, $cardLabels))) {
                    return false;
                }
            }
        }

        if ($rule->filter_member_binding_ids !== null) {
            $cardTrelloMemberIds = $cardDetails['idMembers'] ?? [];

            $localMemberIds = TrelloMember::query()
                ->where('connection_id', $connection->id)
                ->whereIn('trello_member_id', $cardTrelloMemberIds)
                ->pluck('id')
                ->all();

            if (empty(array_intersect($rule->filter_member_binding_ids, $localMemberIds))) {
                return false;
            }
        }

        return true;
    }

    private function buildMentions(TrelloNotificationRule $rule): string
    {
        if (empty($rule->mention_member_binding_ids)) {
            return '';
        }

        $usernames = TrelloMemberBinding::query()
            ->whereIn('trello_member_id', $rule->mention_member_binding_ids)
            ->whereNotNull('telegram_username')
            ->pluck('telegram_username')
            ->all();

        return implode(' ', array_map(fn (string $u) => "@{$u}", $usernames));
    }
}
