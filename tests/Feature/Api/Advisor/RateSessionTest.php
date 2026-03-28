<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", ['rating' => 8])
            ->assertUnauthorized();
    }

    public function test_records_explicit_rating(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", [
            'rating' => 8,
        ])->assertCreated();

        $this->assertDatabaseHas('signals', [
            'user_id'            => $user->id,
            'advisor_session_id' => $session->id,
            'rating'             => 8,
            'type'               => 'explicit',
        ]);
    }

    public function test_accepts_context_and_message_snippet(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", [
            'rating'          => 3,
            'context'         => 'User gave thumbs down',
            'message_snippet' => 'This advice was unhelpful.',
        ])->assertCreated();

        $this->assertDatabaseHas('signals', [
            'advisor_session_id' => $session->id,
            'context'            => 'User gave thumbs down',
            'message_snippet'    => 'This advice was unhelpful.',
        ]);
    }

    public function test_rating_is_required(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_rating_must_be_between_1_and_10(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", ['rating' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", ['rating' => 11])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_blocks_other_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", ['rating' => 7])
            ->assertNotFound();
    }

    public function test_creates_signal_with_default_context_when_not_provided(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/rate", ['rating' => 5]);

        $signal = Signal::where('advisor_session_id', $session->id)->first();
        $this->assertSame('User rated via UI', $signal->context);
    }
}
