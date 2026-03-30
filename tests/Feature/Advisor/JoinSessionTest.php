<?php

namespace Tests\Feature\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        AdvisorSession::factory()->create(['join_token' => 'tok123']);

        $this->get('/join/tok123')->assertRedirect(route('login'));
    }

    public function test_invalid_join_token_returns_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/join/nonexistent')->assertNotFound();
    }

    public function test_get_join_renders_confirmation_page(): void
    {
        $owner   = User::factory()->create(['name' => 'Alice']);
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id, 'join_token' => 'tok123', 'title' => 'My Session']);

        $this->actingAs($joiner);

        $this->get('/join/tok123')
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Join')
                ->where('ownerName', 'Alice')
                ->where('title', 'My Session')
                ->where('token', 'tok123')
            );

        // Should not have joined yet
        $this->assertDatabaseCount('advisor_session_participants', 0);
    }

    public function test_post_join_adds_participant_and_redirects(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($joiner);
        $this->post('/join/tok123')->assertRedirect(route('advisor.show', $session->id));

        $this->assertDatabaseHas('advisor_session_participants', [
            'advisor_session_id' => $session->id,
            'user_id'            => $joiner->id,
        ]);
    }

    public function test_joining_is_idempotent(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($joiner);
        $this->post('/join/tok123');
        $this->post('/join/tok123');

        $this->assertDatabaseCount('advisor_session_participants', 1);
    }

    public function test_already_joined_get_redirects_straight_to_session(): void
    {
        $owner       = User::factory()->create();
        $joiner      = User::factory()->create();
        $session     = AdvisorSession::factory()->withParticipant($joiner)->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($joiner);
        $this->get('/join/tok123')->assertRedirect(route('advisor.show', $session->id));
    }

    public function test_owner_visiting_join_link_redirects_without_joining(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($owner);
        $this->get('/join/tok123')->assertRedirect(route('advisor.show', $session->id));

        $this->assertDatabaseCount('advisor_session_participants', 0);
    }

    public function test_joining_closed_session_redirects_with_error(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        AdvisorSession::factory()->closed()->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($joiner);
        $this->get('/join/tok123')->assertRedirect(route('advisor.index'));
    }

    public function test_post_join_closed_session_redirects_with_error(): void
    {
        $owner  = User::factory()->create();
        $joiner = User::factory()->create();
        AdvisorSession::factory()->closed()->create(['user_id' => $owner->id, 'join_token' => 'tok123']);

        $this->actingAs($joiner);
        $this->post('/join/tok123')->assertRedirect(route('advisor.index'));
    }

    public function test_participated_session_appears_in_index(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        AdvisorSession::factory()->withParticipant($participant)->create(['user_id' => $owner->id]);

        $this->actingAs($participant);

        $this->get(route('advisor.index'))
            ->assertInertia(fn ($page) => $page
                ->where('sessions.total', 1)
            );
    }
}
