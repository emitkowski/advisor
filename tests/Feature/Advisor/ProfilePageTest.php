<?php

namespace Tests\Feature\Advisor;

use App\Models\Learning;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('advisor.profile'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Advisor/Profile'));
    }

    public function test_profile_page_passes_profile_observations(): void
    {
        $user = User::factory()->create();
        Profile::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertInertia(fn ($page) => $page->has('profileObservations', 2));
    }

    public function test_profile_page_passes_learnings(): void
    {
        $user = User::factory()->create();
        Learning::factory()->count(4)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertInertia(fn ($page) => $page->has('learnings', 4));
    }

    public function test_profile_page_passes_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertInertia(fn ($page) => $page->has('projects', 2));
    }

    public function test_profile_page_only_shows_current_users_data(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Learning::factory()->count(2)->create(['user_id' => $user->id]);
        Learning::factory()->count(4)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertInertia(fn ($page) => $page->has('learnings', 2));
    }

    public function test_profile_page_returns_empty_collections_when_no_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.profile'))
            ->assertInertia(fn ($page) => $page
                ->has('profileObservations', 0)
                ->has('learnings', 0)
                ->has('projects', 0)
            );
    }
}
