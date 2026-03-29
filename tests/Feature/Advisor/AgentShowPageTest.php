<?php

namespace Tests\Feature\Advisor;

use App\Models\Agent;
use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentShowPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_authentication(): void
    {
        $agent = Agent::factory()->create();

        $this->get(route('advisor.agents.show', $agent->id))->assertRedirect();
    }

    public function test_show_renders_for_owner(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->get(route('advisor.agents.show', $agent->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Agents/Show')
                ->has('agent')
                ->has('stats')
                ->where('agent.id', $agent->id)
            );
    }

    public function test_show_blocks_other_users_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create();
        Sanctum::actingAs($user);

        $this->get(route('advisor.agents.show', $agent->id))->assertNotFound();
    }

    public function test_stats_count_sessions_for_this_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        AdvisorSession::factory()->count(3)->create(['user_id' => $user->id, 'agent_id' => $agent->id]);
        Sanctum::actingAs($user);

        $this->get(route('advisor.agents.show', $agent->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_sessions', 3)
            );
    }

    public function test_stats_do_not_include_other_agents_sessions(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        $other = Agent::factory()->create(['user_id' => $user->id]);
        AdvisorSession::factory()->count(2)->create(['user_id' => $user->id, 'agent_id' => $other->id]);
        Sanctum::actingAs($user);

        $this->get(route('advisor.agents.show', $agent->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_sessions', 0)
            );
    }
}
