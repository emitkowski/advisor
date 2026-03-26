<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'      => \App\Models\User::factory(),
            'name'         => $this->faker->words(3, true),
            'description'  => $this->faker->sentence(),
            'status'       => 'active',
            'notes'        => null,
            'mentions'     => [],
            'first_seen_at' => now(),
            'last_seen_at'  => now(),
        ];
    }

    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'abandoned']);
    }
}
