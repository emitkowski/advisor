<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'name'                  => $this->faker->words(3, true),
            'description'           => $this->faker->sentence(),
            'color'                 => '#3B82F6',
            'system_prompt_preamble' => $this->faker->paragraph(),
            'personality'           => [
                ['trait' => 'directness', 'value' => 80, 'description' => 'Direct feedback.'],
                ['trait' => 'skepticism',  'value' => 70, 'description' => 'Healthy skepticism.'],
            ],
            'is_preset' => false,
        ];
    }

    public function preset(): static
    {
        return $this->state(['is_preset' => true]);
    }
}
