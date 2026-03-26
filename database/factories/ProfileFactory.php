<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'           => \App\Models\User::factory(),
            'key'               => $this->faker->unique()->word(),
            'value'             => $this->faker->sentence(),
            'confidence'        => $this->faker->randomFloat(3, 0.4, 1.0),
            'observation_count' => 1,
        ];
    }
}
