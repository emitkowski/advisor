<?php

namespace Tests\Unit\Models;

use App\Models\AdvisorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_usd_is_zero_when_no_tokens_used(): void
    {
        $session = AdvisorSession::factory()->create(['input_tokens' => 0, 'output_tokens' => 0]);

        $this->assertSame(0.0, $session->cost_usd);
    }

    public function test_cost_usd_is_calculated_from_token_counts(): void
    {
        $session = AdvisorSession::factory()->create([
            'input_tokens'  => 1_000_000,
            'output_tokens' => 1_000_000,
        ]);

        $pricing  = config('advisor.pricing.' . config('advisor.model'));
        $expected = round(
            (1_000_000 / 1_000_000) * $pricing['input_per_million'] +
            (1_000_000 / 1_000_000) * $pricing['output_per_million'],
            4
        );

        $this->assertEqualsWithDelta($expected, $session->cost_usd, 0.0001);
    }

    public function test_cost_usd_is_appended_to_json(): void
    {
        $session = AdvisorSession::factory()->create(['input_tokens' => 1000, 'output_tokens' => 500]);

        $this->assertArrayHasKey('cost_usd', $session->toArray());
    }

    public function test_accumulate_tokens_increments_both_columns(): void
    {
        $session = AdvisorSession::factory()->create(['input_tokens' => 100, 'output_tokens' => 50]);

        $session->accumulateTokens(200, 75);

        $fresh = $session->fresh();
        $this->assertSame(300, $fresh->input_tokens);
        $this->assertSame(125, $fresh->output_tokens);
    }

    public function test_accumulate_tokens_ignores_zero_values(): void
    {
        $session = AdvisorSession::factory()->create(['input_tokens' => 100, 'output_tokens' => 50]);

        $session->accumulateTokens(0, 0);

        $fresh = $session->fresh();
        $this->assertSame(100, $fresh->input_tokens);
        $this->assertSame(50, $fresh->output_tokens);
    }
}
