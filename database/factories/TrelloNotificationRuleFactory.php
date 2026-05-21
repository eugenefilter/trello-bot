<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TrelloConnection;
use App\Models\TrelloNotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrelloNotificationRule>
 */
class TrelloNotificationRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'connection_id' => TrelloConnection::factory(),
            'name' => $this->faker->words(3, true),
            'is_active' => true,
            'trigger_list_id' => $this->faker->regexify('[a-zA-Z0-9]{24}'),
            'filter_label_ids' => null,
            'filter_label_mode' => 'any',
            'filter_member_binding_ids' => null,
            'telegram_chat_id' => '-'.$this->faker->numberBetween(1000000000, 9999999999),
            'mention_member_binding_ids' => null,
            'message_template' => 'Карточка {{card_name}} перемещена в {{list_name}} {{mentions}}',
        ];
    }

    public function withLabels(array $labelIds, string $mode = 'any'): static
    {
        return $this->state(fn () => [
            'filter_label_ids' => $labelIds,
            'filter_label_mode' => $mode,
        ]);
    }

    public function withMembers(array $bindingIds): static
    {
        return $this->state(fn () => [
            'filter_member_binding_ids' => $bindingIds,
        ]);
    }

    public function withMentions(array $bindingIds): static
    {
        return $this->state(fn () => [
            'mention_member_binding_ids' => $bindingIds,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
