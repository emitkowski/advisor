<?php

namespace Database\Factories;

use App\Models\Learning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Learning>
 */
class LearningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = ['blind_spot', 'pattern', 'follow_through', 'value', 'reaction', 'domain'];

        return [
            'user_id'            => \App\Models\User::factory(),
            'advisor_session_id' => \App\Models\AdvisorSession::factory(),
            'category'           => $this->faker->randomElement($categories),
            'content'            => $this->faker->sentence(),
            'confidence'         => $this->faker->randomFloat(3, 0.5, 1.0),
            'reinforcement_count' => 1,
            'last_seen_at'       => now(),
        ];
    }
}
