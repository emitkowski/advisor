<?php

namespace Tests\Feature\Advisor;

use App\Models\Agent;
use App\Models\AdvisorSession;
use App\Models\User;
use App\Services\AnthropicService;
use App\Services\SystemPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AgentPickerTest extends TestCase
{
    use RefreshDatabase;

    // --- Session creation with agent ---

    public function test_store_creates_session_with_selected_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('advisor.store'), ['agent_id' => $agent->id])
            ->assertRedirect();

        $session = AdvisorSession::where('user_id', $user->id)->first();
        $this->assertSame($agent->id, $session->agent_id);
    }

    public function test_store_creates_session_without_agent_when_not_provided(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('advisor.store'))
            ->assertRedirect();

        $session = AdvisorSession::where('user_id', $user->id)->first();
        $this->assertNull($session->agent_id);
    }

    public function test_store_rejects_another_users_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(); // belongs to a different user

        $this->actingAs($user)
            ->post(route('advisor.store'), ['agent_id' => $agent->id])
            ->assertNotFound();
    }

    public function test_index_seeds_preset_agents_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('advisor.index'));

        $this->assertSame(6, Agent::where('user_id', $user->id)->count());
    }

    public function test_index_passes_agents_to_inertia(): void
    {
        $user = User::factory()->create();
        Agent::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.index'))
            ->assertInertia(fn ($page) => $page->has('agents', 8)); // 2 custom + 6 seeded presets
    }

    // --- SystemPromptBuilder with agent ---

    public function test_system_prompt_uses_agent_preamble_when_agent_set(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create([
            'user_id'               => $user->id,
            'system_prompt_preamble' => 'You are a custom product advisor.',
            'personality'           => [
                ['trait' => 'directness', 'value' => 70, 'description' => 'Direct.'],
            ],
        ]);

        $prompt = (new SystemPromptBuilder($user->id, $agent))->build();

        $this->assertStringContainsString('You are a custom product advisor.', $prompt);
        $this->assertStringNotContainsString('brutally honest intellectual advisor', $prompt);
    }

    public function test_system_prompt_uses_default_identity_without_agent(): void
    {
        $user = User::factory()->create();

        $prompt = (new SystemPromptBuilder($user->id))->build();

        $this->assertStringContainsString('brutally honest intellectual advisor', $prompt);
    }

    public function test_system_prompt_always_injects_memory_regardless_of_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $prompt = (new SystemPromptBuilder($user->id, $agent))->build();

        $this->assertStringContainsString('# Memory', $prompt);
    }

    public function test_system_prompt_uses_agent_personality_not_user_traits(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create([
            'user_id'     => $user->id,
            'personality' => [
                ['trait' => 'custom_trait', 'value' => 42, 'description' => 'A unique trait.'],
            ],
        ]);

        $prompt = (new SystemPromptBuilder($user->id, $agent))->build();

        $this->assertStringContainsString('custom_trait', $prompt);
        $this->assertStringContainsString('42/100', $prompt);
    }
}
