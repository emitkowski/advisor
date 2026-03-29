<?php

namespace Tests\Feature\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_generates_token_and_returns_url(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/advisor/sessions/{$session->id}/share")
            ->assertOk()
            ->assertJsonStructure(['share_url']);

        $this->assertNotNull($session->fresh()->share_token);
    }

    public function test_share_is_idempotent(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id, 'share_token' => 'existing-token-123456789012']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/advisor/sessions/{$session->id}/share")
            ->assertOk();

        $this->assertSame('existing-token-123456789012', $session->fresh()->share_token);
    }

    public function test_unshare_revokes_token(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id, 'share_token' => 'some-token-1234567890123456']);

        $this->actingAs($user)
            ->deleteJson("/api/v1/advisor/sessions/{$session->id}/share")
            ->assertOk();

        $this->assertNull($session->fresh()->share_token);
    }

    public function test_cannot_share_another_users_session(): void
    {
        $owner  = User::factory()->create();
        $other  = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson("/api/v1/advisor/sessions/{$session->id}/share")
            ->assertNotFound();
    }

    public function test_shared_session_is_publicly_viewable(): void
    {
        $session = AdvisorSession::factory()->create([
            'title'       => 'My Public Session',
            'share_token' => 'public-token-1234567890123456',
        ]);

        $this->get("/shared/public-token-1234567890123456")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Advisor/Shared')
                ->where('session.title', 'My Public Session')
            );
    }

    public function test_shared_session_does_not_expose_sensitive_fields(): void
    {
        $session = AdvisorSession::factory()->create([
            'share_token' => 'safe-token-12345678901234567',
        ]);

        $this->get("/shared/safe-token-12345678901234567")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('session.user_id')
                ->missing('session.share_token')
            );
    }

    public function test_invalid_share_token_returns_404(): void
    {
        $this->get('/shared/nonexistent-token')->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_generate_share_link(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/share")
            ->assertUnauthorized();
    }

    public function test_shared_page_includes_og_meta_with_summary(): void
    {
        $session = AdvisorSession::factory()->create([
            'title'       => 'Pricing Strategy Discussion',
            'summary'     => 'The user explored tiered pricing options for a B2B SaaS product.',
            'share_token' => 'og-token-123456789012345678901',
        ]);

        $this->get("/shared/og-token-123456789012345678901")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('meta.title', 'Pricing Strategy Discussion')
                ->where('meta.description', 'The user explored tiered pricing options for a B2B SaaS product.')
                ->has('meta.url')
                ->has('meta.site_name')
            );
    }

    public function test_shared_page_falls_back_to_first_message_when_no_summary(): void
    {
        $thread  = [
            ['role' => 'user', 'content' => 'I want to discuss my go-to-market strategy.'],
            ['role' => 'assistant', 'content' => 'Sure, let\'s dig in.'],
        ];
        $session = AdvisorSession::factory()->create([
            'title'       => 'GTM Planning',
            'summary'     => null,
            'thread'      => $thread,
            'share_token' => 'fallback-token-1234567890123456',
        ]);

        $this->get("/shared/fallback-token-1234567890123456")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('meta.description', 'I want to discuss my go-to-market strategy.')
            );
    }
}
