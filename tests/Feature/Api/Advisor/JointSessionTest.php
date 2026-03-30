<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JointSessionTest extends TestCase
{
    use RefreshDatabase;

    // --- show (polling) ---

    public function test_participant_can_fetch_session_via_show(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        $session     = AdvisorSession::factory()->withParticipant($participant)->create(['user_id' => $owner->id]);
        Sanctum::actingAs($participant);

        $this->getJson("/api/v1/advisor/sessions/{$session->id}")->assertOk();
    }

    public function test_non_participant_cannot_fetch_others_session(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/advisor/sessions/{$session->id}")->assertNotFound();
    }

    // --- addMessage with sender identity ---

    public function test_add_message_stores_user_id_and_user_name(): void
    {
        $owner   = User::factory()->create(['name' => 'Alice']);
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $session->addMessage('user', 'Hello there', $owner->id, $owner->name);

        $thread = $session->fresh()->thread;
        $this->assertSame($owner->id, $thread[0]['user_id']);
        $this->assertSame('Alice', $thread[0]['user_name']);
    }

    public function test_add_message_without_user_id_is_backward_compatible(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $session->addMessage('user', 'Hello');

        $thread = $session->fresh()->thread;
        $this->assertArrayNotHasKey('user_id', $thread[0]);
        $this->assertArrayNotHasKey('user_name', $thread[0]);
    }

    public function test_get_api_messages_strips_user_id_and_user_name(): void
    {
        $owner   = User::factory()->create(['name' => 'Alice']);
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $session->addMessage('user', 'Hello', $owner->id, $owner->name);

        $apiMessages = $session->fresh()->getApiMessages();
        $this->assertArrayNotHasKey('user_id', $apiMessages[0]);
        $this->assertArrayNotHasKey('user_name', $apiMessages[0]);
        $this->assertSame('user', $apiMessages[0]['role']);
        $this->assertSame('Hello', $apiMessages[0]['content']);
    }

    // --- isAccessibleBy ---

    public function test_is_accessible_by_owner(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($session->isAccessibleBy($owner->id));
    }

    public function test_is_accessible_by_participant(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        $session     = AdvisorSession::factory()->withParticipant($participant)->create(['user_id' => $owner->id]);

        $this->assertTrue($session->isAccessibleBy($participant->id));
    }

    public function test_is_not_accessible_by_random_user(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($session->isAccessibleBy($other->id));
    }

    // --- chat page props ---

    public function test_owner_sees_is_owner_true(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($owner);

        $response = $this->get(route('advisor.show', $session->id));
        $response->assertInertia(fn ($page) => $page->where('isOwner', true));
    }

    public function test_participant_sees_is_owner_false(): void
    {
        $owner       = User::factory()->create();
        $participant = User::factory()->create();
        $session     = AdvisorSession::factory()->withParticipant($participant)->create(['user_id' => $owner->id]);
        $this->actingAs($participant);

        $response = $this->get(route('advisor.show', $session->id));
        $response->assertInertia(fn ($page) => $page->where('isOwner', false));
    }

    public function test_non_participant_cannot_access_chat_page(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($other);

        $this->get(route('advisor.show', $session->id))->assertNotFound();
    }
}
