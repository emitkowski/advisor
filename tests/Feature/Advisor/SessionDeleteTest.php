<?php

namespace Tests\Feature\Advisor;

use App\Models\AdvisorSession;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_delete_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->for($user)->create();

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}")
            ->assertUnauthorized();
    }

    public function test_user_can_delete_their_own_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}")
            ->assertOk()
            ->assertJson(['message' => 'Session deleted.']);

        $this->assertDatabaseMissing('advisor_sessions', ['id' => $session->id]);
    }

    public function test_deleting_session_cascades_signals(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->for($user)->create();

        Signal::factory()->for($user)->for($session, 'session')->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}")->assertOk();

        $this->assertDatabaseMissing('signals', ['advisor_session_id' => $session->id]);
    }

    public function test_user_cannot_delete_another_users_session(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $session = AdvisorSession::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/advisor/sessions/{$session->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('advisor_sessions', ['id' => $session->id]);
    }
}
