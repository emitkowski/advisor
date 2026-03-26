<?php

namespace Database\Factories;

use App\Models\AdvisorSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvisorSession>
 */
class AdvisorSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'       => \App\Models\User::factory(),
            'title'         => null,
            'thread'        => null,
            'message_count' => 0,
            'started_at'    => now(),
            'ended_at'      => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at'   => now(),
            'avg_rating' => $this->faker->randomFloat(2, 1, 10),
        ]);
    }

    public function withMessages(int $count = 4): static
    {
        return $this->state(function (array $attributes) use ($count) {
            $thread = [];
            for ($i = 0; $i < $count; $i++) {
                $thread[] = [
                    'role'      => $i % 2 === 0 ? 'user' : 'assistant',
                    'content'   => $this->faker->paragraph(),
                    'timestamp' => now()->subMinutes($count - $i)->toISOString(),
                ];
            }

            return [
                'thread'        => $thread,
                'message_count' => $count,
            ];
        });
    }
}
