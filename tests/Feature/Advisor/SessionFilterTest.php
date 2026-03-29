<?php

namespace Tests\Feature\Advisor;

use App\Models\Agent;
use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_search_filters_by_title(): void
    {
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'title' => 'Fundraising strategy']);
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'title' => 'Technical architecture']);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['search' => 'Fundraising']))
            ->assertInertia(fn ($page) => $page
                ->has('sessions.data', 1)
                ->where('sessions.data.0.title', 'Fundraising strategy')
            );
    }

    public function test_search_is_case_insensitive(): void
    {
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'title' => 'Pricing Model Review']);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['search' => 'pricing model']))
            ->assertInertia(fn ($page) => $page->has('sessions.data', 1));
    }

    public function test_agent_filter_returns_matching_sessions(): void
    {
        $agent = Agent::factory()->create(['user_id' => $this->user->id]);

        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'agent_id' => $agent->id]);
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'agent_id' => null]);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['agent_id' => $agent->id]))
            ->assertInertia(fn ($page) => $page->has('sessions.data', 1));
    }

    public function test_status_filter_active_returns_open_sessions(): void
    {
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'ended_at' => null]);
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'ended_at' => now()]);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['status' => 'active']))
            ->assertInertia(fn ($page) => $page->has('sessions.data', 1));
    }

    public function test_status_filter_closed_returns_ended_sessions(): void
    {
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'ended_at' => null]);
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'ended_at' => now()]);
        AdvisorSession::factory()->create(['user_id' => $this->user->id, 'ended_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['status' => 'closed']))
            ->assertInertia(fn ($page) => $page->has('sessions.data', 2));
    }

    public function test_filters_are_passed_back_to_inertia(): void
    {
        $this->actingAs($this->user)
            ->get(route('advisor.index', ['search' => 'test', 'status' => 'active']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.search', 'test')
                ->where('filters.status', 'active')
            );
    }

    public function test_filters_do_not_leak_between_users(): void
    {
        $other = User::factory()->create();
        AdvisorSession::factory()->create(['user_id' => $other->id, 'title' => 'Secret session']);

        $this->actingAs($this->user)
            ->get(route('advisor.index', ['search' => 'Secret']))
            ->assertInertia(fn ($page) => $page->has('sessions.data', 0));
    }
}
