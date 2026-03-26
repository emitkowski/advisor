<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    // --- Index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/advisor/sessions')->assertUnauthorized();
    }

    public function test_index_returns_paginated_sessions_for_current_user(): void
    {
        $user = User::factory()->create();
        AdvisorSession::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/advisor/sessions')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_does_not_return_other_users_sessions(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        AdvisorSession::factory()->count(2)->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/advisor/sessions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // --- Store ---

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/advisor/sessions')->assertUnauthorized();
    }

    public function test_store_creates_session(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/advisor/sessions')
            ->assertCreated()
            ->assertJsonStructure(['id', 'user_id', 'started_at']);

        $this->assertDatabaseHas('advisor_sessions', ['user_id' => $user->id]);
    }

    public function test_store_seeds_personality_traits(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/advisor/sessions')->assertCreated();

        $this->assertSame(7, PersonalityTrait::where('user_id', $user->id)->count());
    }

    // --- Show ---

    public function test_show_requires_authentication(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->getJson("/api/v1/advisor/sessions/{$session->id}")->assertUnauthorized();
    }

    public function test_show_returns_session_for_owner(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/advisor/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('id', $session->id);
    }

    public function test_show_blocks_other_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/advisor/sessions/{$session->id}")->assertNotFound();
    }
}
