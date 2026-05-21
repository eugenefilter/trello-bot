<?php

namespace Database\Factories;

use App\Models\TrelloConnection;
use App\Models\TrelloMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrelloMember>
 */
class TrelloMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => TrelloConnection::factory(),
            'trello_member_id' => $this->faker->regexify('[a-zA-Z0-9]{24}'),
            'username' => $this->faker->userName(),
            'full_name' => $this->faker->name(),
            'is_active' => true,
        ];
    }
}
