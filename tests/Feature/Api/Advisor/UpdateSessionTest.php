<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->patchJson("/api/v1/advisor/sessions/{$session->id}", ['title' => 'New Title'])
            ->assertUnauthorized();
    }

    public function test_updates_session_title(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/sessions/{$session->id}", ['title' => 'My Custom Title'])
            ->assertOk()
            ->assertJsonPath('title', 'My Custom Title');

        $this->assertSame('My Custom Title', $session->fresh()->title);
    }

    public function test_title_is_required(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/sessions/{$session->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_title_cannot_exceed_120_characters(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/sessions/{$session->id}", ['title' => str_repeat('a', 121)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_cannot_update_another_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/sessions/{$session->id}", ['title' => 'Hijacked'])
            ->assertNotFound();
    }
}
