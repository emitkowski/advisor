<?php

namespace Tests\Unit\Models;

use App\Models\Learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reinforce_increases_confidence(): void
    {
        $learning = Learning::factory()->create(['confidence' => 0.6, 'reinforcement_count' => 1]);

        $learning->reinforce();

        $this->assertEqualsWithDelta(0.65, $learning->fresh()->confidence, 0.001);
    }

    public function test_reinforce_increments_count(): void
    {
        $learning = Learning::factory()->create(['reinforcement_count' => 2]);

        $learning->reinforce();

        $this->assertSame(3, $learning->fresh()->reinforcement_count);
    }

    public function test_reinforce_caps_confidence_at_one(): void
    {
        $learning = Learning::factory()->create(['confidence' => 0.98]);

        $learning->reinforce();

        $this->assertEqualsWithDelta(1.0, $learning->fresh()->confidence, 0.001);
    }

    public function test_build_context_block_returns_empty_string_when_no_learnings(): void
    {
        $user = User::factory()->create();

        $this->assertSame('', Learning::buildContextBlock($user->id));
    }

    public function test_build_context_block_excludes_low_confidence_learnings(): void
    {
        $user = User::factory()->create();
        Learning::factory()->create(['user_id' => $user->id, 'confidence' => 0.3, 'category' => 'pattern']);

        $this->assertSame('', Learning::buildContextBlock($user->id));
    }

    public function test_build_context_block_includes_high_confidence_learnings(): void
    {
        $user = User::factory()->create();
        Learning::factory()->create([
            'user_id'    => $user->id,
            'category'   => 'blind_spot',
            'content'    => 'Tends to skip validation',
            'confidence' => 0.8,
        ]);

        $block = Learning::buildContextBlock($user->id);

        $this->assertStringContainsString('Known blind spots', $block);
        $this->assertStringContainsString('Tends to skip validation', $block);
    }

    public function test_build_context_block_groups_by_category(): void
    {
        $user = User::factory()->create();
        Learning::factory()->create(['user_id' => $user->id, 'category' => 'pattern', 'confidence' => 0.7]);
        Learning::factory()->create(['user_id' => $user->id, 'category' => 'value', 'confidence' => 0.7]);

        $block = Learning::buildContextBlock($user->id);

        $this->assertStringContainsString('Thinking patterns', $block);
        $this->assertStringContainsString('What you say matters', $block);
    }
}
