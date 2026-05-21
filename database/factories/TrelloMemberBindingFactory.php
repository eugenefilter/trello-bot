<?php

namespace Database\Factories;

use App\Models\TrelloMember;
use App\Models\TrelloMemberBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrelloMemberBinding>
 */
class TrelloMemberBindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trello_member_id' => TrelloMember::factory(),
            'telegram_user_id' => null,
            'telegram_username' => null,
            'telegram_full_name' => null,
        ];
    }

    public function withTelegram(): static
    {
        return $this->state(fn () => [
            'telegram_user_id' => (string) $this->faker->numberBetween(100000000, 999999999),
            'telegram_username' => $this->faker->userName(),
            'telegram_full_name' => $this->faker->name(),
        ]);
    }
}
