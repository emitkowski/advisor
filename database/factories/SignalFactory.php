<?php

namespace Database\Factories;

use App\Models\Signal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signal>
 */
class SignalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'            => \App\Models\User::factory(),
            'advisor_session_id' => \App\Models\AdvisorSession::factory(),
            'rating'             => $this->faker->randomFloat(2, 1, 10),
            'type'               => 'implicit',
            'sentiment'          => $this->faker->randomFloat(3, 0, 1),
            'context'            => $this->faker->sentence(),
            'message_snippet'    => null,
        ];
    }

    public function explicit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'            => 'explicit',
            'sentiment'       => null,
            'message_snippet' => $this->faker->sentence(),
        ]);
    }
}
