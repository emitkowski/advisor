<?php

namespace Database\Factories;

use App\Models\PersonalityTrait;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalityTrait>
 */
class PersonalityTraitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => \App\Models\User::factory(),
            'trait'       => $this->faker->unique()->word(),
            'value'       => $this->faker->numberBetween(0, 100),
            'description' => $this->faker->sentence(),
            'is_system'   => false,
        ];
    }
}
