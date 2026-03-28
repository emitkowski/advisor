<?php

namespace Tests\Feature\Advisor;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentManagementTest extends TestCase
{
    use RefreshDatabase;

    // --- agents index ---

    public function test_agents_index_requires_authentication(): void
    {
        $this->get(route('advisor.agents'))->assertRedirect(route('login'));
    }

    public function test_agents_index_renders_inertia_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.agents'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Advisor/Agents/Index'));
    }

    public function test_agents_index_passes_only_current_users_agents(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Agent::factory()->count(3)->create(['user_id' => $user->id]);
        Agent::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('advisor.agents'))
            ->assertInertia(fn ($page) => $page->has('agents', 3));
    }

    // --- create ---

    public function test_agent_create_requires_authentication(): void
    {
        $this->get(route('advisor.agents.create'))->assertRedirect(route('login'));
    }

    public function test_agent_create_renders_inertia_page_with_null_agent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.agents.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Agents/Form')
                ->where('agent', null)
            );
    }

    // --- edit ---

    public function test_agent_edit_requires_authentication(): void
    {
        $agent = Agent::factory()->create();
        $this->get(route('advisor.agents.edit', $agent->id))->assertRedirect(route('login'));
    }

    public function test_agent_edit_renders_inertia_page_with_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.agents.edit', $agent->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Agents/Form')
                ->where('agent.id', $agent->id)
            );
    }

    public function test_agent_edit_returns_404_for_another_users_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(); // different user

        $this->actingAs($user)
            ->get(route('advisor.agents.edit', $agent->id))
            ->assertNotFound();
    }
}
