<?php

namespace Tests\Unit\Services;

use App\Models\Agent;
use App\Services\SystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_agent_algorithm_when_set(): void
    {
        $agent = Agent::factory()->make([
            'algorithm'             => '# Custom Algorithm',
            'system_prompt_preamble' => '# Custom Identity',
            'personality'           => [],
        ]);

        $prompt = (new SystemPromptBuilder(1, $agent))->build();

        $this->assertStringContainsString('# Custom Algorithm', $prompt);
    }

    public function test_uses_default_algorithm_when_agent_has_none(): void
    {
        $agent = Agent::factory()->make([
            'algorithm'             => null,
            'system_prompt_preamble' => '# Custom Identity',
            'personality'           => [],
        ]);

        $prompt = (new SystemPromptBuilder(1, $agent))->build();

        $this->assertStringContainsString('# The Algorithm', $prompt);
        $this->assertStringContainsString('OBSERVE', $prompt);
    }

    public function test_uses_default_algorithm_when_no_agent(): void
    {
        $prompt = (new SystemPromptBuilder(1, null))->build();

        $this->assertStringContainsString('# The Algorithm', $prompt);
        $this->assertStringContainsString('OBSERVE', $prompt);
    }

    public function test_each_preset_agent_has_a_distinct_algorithm(): void
    {
        $algorithms = collect(Agent::presets())
            ->pluck('algorithm')
            ->filter()
            ->unique()
            ->values();

        $this->assertCount(5, $algorithms, 'Each preset agent should have a unique algorithm.');
    }
}
