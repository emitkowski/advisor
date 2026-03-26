<?php

namespace Tests\Feature\Api\Advisor;

use App\Jobs\ProcessSessionLearning;
use App\Models\AdvisorSession;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CloseSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/close")->assertUnauthorized();
    }

    public function test_closes_active_session(): void
    {
        Queue::fake();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/close")->assertOk();

        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_dispatches_learning_job(): void
    {
        Queue::fake();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/close");

        Queue::assertPushedOn('learning', ProcessSessionLearning::class);
    }

    public function test_computes_average_rating_on_close(): void
    {
        Queue::fake();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Signal::factory()->create(['user_id' => $user->id, 'advisor_session_id' => $session->id, 'rating' => 8.0]);
        Signal::factory()->create(['user_id' => $user->id, 'advisor_session_id' => $session->id, 'rating' => 6.0]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/close");

        $response->assertOk()->assertJsonPath('avg_rating', '7.00');
    }

    public function test_returns_422_for_already_closed_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->closed()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/close")->assertUnprocessable();
    }

    public function test_blocks_other_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/close")->assertNotFound();
    }
}
