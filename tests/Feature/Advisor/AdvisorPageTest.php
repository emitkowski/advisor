<?php

namespace Tests\Feature\Advisor;

use App\Models\AdvisorSession;
use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorPageTest extends TestCase
{
    use RefreshDatabase;

    // --- Index ---

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get(route('advisor.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Advisor/Index'));
    }

    public function test_index_only_shows_current_users_sessions(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        AdvisorSession::factory()->create(['user_id' => $user->id]);
        AdvisorSession::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('advisor.index'))
            ->assertInertia(fn ($page) => $page
                ->has('sessions.data', 1)
            );
    }

    // --- Store ---

    public function test_guest_cannot_create_session(): void
    {
        $this->post(route('advisor.store'))->assertRedirect(route('login'));
    }

    public function test_store_creates_session_and_redirects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('advisor.store'))
            ->assertRedirect();

        $this->assertDatabaseHas('advisor_sessions', ['user_id' => $user->id]);
    }

    public function test_store_seeds_personality_traits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('advisor.store'));

        $this->assertSame(13, PersonalityTrait::where('user_id', $user->id)->count());
    }

    public function test_store_redirects_to_the_new_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('advisor.store'));

        $session = AdvisorSession::where('user_id', $user->id)->first();
        $response->assertRedirect(route('advisor.show', $session->id));
    }

    // --- Show ---

    public function test_guest_is_redirected_from_show(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->get(route('advisor.show', $session->id))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_their_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('advisor.show', $session->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Chat')
                ->where('session.id', $session->id)
            );
    }

    public function test_user_cannot_view_another_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();

        $this->actingAs($user)
            ->get(route('advisor.show', $session->id))
            ->assertNotFound();
    }
}
