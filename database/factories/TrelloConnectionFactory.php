<?php

namespace Database\Factories;

use App\Models\TrelloConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrelloConnection>
 */
class TrelloConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'api_key' => $this->faker->md5(),
            'api_token' => $this->faker->sha256(),
            'board_id' => $this->faker->regexify('[a-zA-Z0-9]{8}'),
            'is_active' => true,
            'webhook_token' => $this->faker->uuid(),
        ];
    }
}
