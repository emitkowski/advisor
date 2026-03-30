<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeaveSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_leave(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertUnauthorized();
    }

    public function test_participant_can_leave_session(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->withParticipant($joiner)->create(['user_id' => $owner->id]);

        Sanctum::actingAs($joiner);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertOk();

        $this->assertDatabaseMissing('advisor_session_participants', [
            'advisor_session_id' => $session->id,
            'user_id'            => $joiner->id,
        ]);
    }

    public function test_messages_remain_after_leaving(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->withParticipant($joiner)->create([
            'user_id' => $owner->id,
            'thread'  => [
                ['role' => 'user', 'content' => 'Hello', 'user_id' => $joiner->id, 'user_name' => $joiner->name],
                ['role' => 'assistant', 'content' => 'Hi there'],
            ],
        ]);

        Sanctum::actingAs($joiner);
        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertOk();

        $this->assertCount(2, $session->fresh()->thread);
    }

    public function test_owner_cannot_leave_their_own_session(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertUnprocessable();
    }

    public function test_non_participant_gets_404(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $session  = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($stranger);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertNotFound();
    }

    public function test_after_leaving_second_call_returns_404(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->withParticipant($joiner)->create(['user_id' => $owner->id]);

        Sanctum::actingAs($joiner);
        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertOk();
        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertNotFound();
    }

    public function test_session_no_longer_appears_in_participant_index_after_leaving(): void
    {
        $owner   = User::factory()->create();
        $joiner  = User::factory()->create();
        $session = AdvisorSession::factory()->withParticipant($joiner)->create(['user_id' => $owner->id]);

        Sanctum::actingAs($joiner);
        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/leave")->assertOk();

        $this->actingAs($joiner);
        $this->get(route('advisor.index'))
            ->assertInertia(fn ($page) => $page->where('sessions.total', 0));
    }
}
