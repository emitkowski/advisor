<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JoinLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_generate_join_link(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertUnauthorized();
    }

    public function test_owner_can_generate_join_link(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertOk();

        $this->assertNotNull($response->json('join_url'));
        $this->assertDatabaseHas('advisor_sessions', ['id' => $session->id, 'join_token' => $session->fresh()->join_token]);
    }

    public function test_generate_join_link_is_idempotent(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $first  = $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->json('join_url');
        $second = $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->json('join_url');

        $this->assertSame($first, $second);
    }

    public function test_participant_cannot_generate_join_link(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        $session     = AdvisorSession::factory()->withParticipant($participant)->create(['user_id' => $owner->id]);
        Sanctum::actingAs($participant);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertNotFound();
    }

    public function test_cannot_generate_join_link_for_closed_session(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->closed()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertStatus(422);
    }

    public function test_owner_can_revoke_join_link(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id, 'join_token' => 'abc123']);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertOk();

        $this->assertNull($session->fresh()->join_token);
    }

    public function test_revoking_does_not_remove_existing_participants(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        $session     = AdvisorSession::factory()
            ->withParticipant($participant)
            ->create(['user_id' => $owner->id, 'join_token' => 'abc123']);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}/join-link")->assertOk();

        $this->assertTrue($session->fresh()->isAccessibleBy($participant->id));
    }
}
