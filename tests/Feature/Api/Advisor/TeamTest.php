<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\Agent;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    // --- current ---

    public function test_current_requires_authentication(): void
    {
        $this->getJson('/api/v1/advisor/teams/current')->assertUnauthorized();
    }

    public function test_current_returns_null_when_no_team(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/advisor/teams/current')
            ->assertOk();
    }

    public function test_current_returns_team_with_members_and_pending_invitations(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        $owner->update(['current_team_id' => $team->id]);

        TeamInvitation::generate($team, 'pending@example.com', $owner->id);
        // accepted invite — should not appear
        TeamInvitation::generate($team, 'done@example.com', $owner->id)->update(['accepted_at' => now()]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/advisor/teams/current')->assertOk();

        $this->assertSame('Acme', $response->json('name'));
        $this->assertCount(2, $response->json('members'));
        $this->assertCount(1, $response->json('invitations'));
        $this->assertSame('pending@example.com', $response->json('invitations.0.email'));
    }

    // --- store ---

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/advisor/teams', ['name' => 'Acme'])->assertUnauthorized();
    }

    public function test_store_creates_team_and_sets_current_team(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/advisor/teams', ['name' => 'My Team'])->assertCreated();

        $this->assertDatabaseHas('teams', ['owner_id' => $user->id, 'name' => 'My Team']);
        $this->assertDatabaseHas('team_members', ['user_id' => $user->id]);
        $this->assertSame('My Team', $user->fresh()->currentOrOwnedTeam()->name);
    }

    public function test_store_rejects_duplicate_team_ownership(): void
    {
        $owner = User::factory()->create();
        Team::create(['owner_id' => $owner->id, 'name' => 'First']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/advisor/teams', ['name' => 'Second'])->assertStatus(400);
    }

    // --- invite ---

    public function test_invite_requires_authentication(): void
    {
        $this->postJson('/api/v1/advisor/teams/invite', ['email' => 'x@example.com'])->assertUnauthorized();
    }

    public function test_invite_sends_email_and_stores_invitation(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach($owner->id);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/advisor/teams/invite', ['email' => 'newbie@example.com'])
            ->assertOk()
            ->assertJsonFragment(['message' => 'Invitation sent.']);

        $this->assertDatabaseHas('team_invitations', ['email' => 'newbie@example.com', 'team_id' => $team->id]);
        Notification::assertSentOnDemand(TeamInvitationNotification::class);
    }

    public function test_invite_revokes_previous_pending_before_sending_new_one(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach($owner->id);
        Sanctum::actingAs($owner);

        TeamInvitation::generate($team, 'repeat@example.com', $owner->id);
        $this->assertDatabaseCount('team_invitations', 1);

        $this->postJson('/api/v1/advisor/teams/invite', ['email' => 'repeat@example.com'])->assertOk();

        $this->assertDatabaseCount('team_invitations', 1);
    }

    public function test_invite_rejected_for_non_owner(): void
    {
        $member = User::factory()->create();
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/advisor/teams/invite', ['email' => 'x@example.com'])->assertForbidden();
    }

    public function test_invite_rejects_existing_member(): void
    {
        Notification::fake();

        $owner  = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/advisor/teams/invite', ['email' => 'member@example.com'])
            ->assertStatus(422);
    }

    // --- showInvitation ---

    public function test_show_invitation_returns_valid_details(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $inv   = TeamInvitation::generate($team, 'guest@example.com', $owner->id);

        $this->getJson("/api/v1/advisor/teams/invitations/{$inv->token}")
            ->assertOk()
            ->assertJsonFragment(['valid' => true, 'email' => 'guest@example.com', 'team_name' => 'Acme']);
    }

    public function test_show_invitation_returns_invalid_for_expired(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $inv   = TeamInvitation::generate($team, 'guest@example.com', $owner->id);
        $inv->update(['expires_at' => now()->subDay()]);

        $this->getJson("/api/v1/advisor/teams/invitations/{$inv->token}")
            ->assertOk()
            ->assertJsonFragment(['valid' => false, 'reason' => 'expired']);
    }

    public function test_show_invitation_returns_invalid_for_accepted(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $inv   = TeamInvitation::generate($team, 'guest@example.com', $owner->id);
        $inv->update(['accepted_at' => now()]);

        $this->getJson("/api/v1/advisor/teams/invitations/{$inv->token}")
            ->assertOk()
            ->assertJsonFragment(['valid' => false, 'reason' => 'already_accepted']);
    }

    // --- acceptInvitation ---

    public function test_accept_invitation_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $inv   = TeamInvitation::generate($team, 'guest@example.com', $owner->id);

        $this->postJson("/api/v1/advisor/teams/invitations/{$inv->token}/accept")->assertUnauthorized();
    }

    public function test_accept_invitation_adds_member_and_sets_current_team(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach($owner->id);

        $guest = User::factory()->create(['email' => 'guest@example.com']);
        $inv   = TeamInvitation::generate($team, 'guest@example.com', $owner->id);

        Sanctum::actingAs($guest);

        $this->postJson("/api/v1/advisor/teams/invitations/{$inv->token}/accept")->assertOk();

        $this->assertTrue($team->hasMember($guest));
        $this->assertSame($team->id, $guest->fresh()->current_team_id);
        $this->assertNotNull($inv->fresh()->accepted_at);
    }

    public function test_accept_invitation_rejects_wrong_email(): void
    {
        $owner = User::factory()->create();
        $team  = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $inv   = TeamInvitation::generate($team, 'other@example.com', $owner->id);

        $wrongUser = User::factory()->create(['email' => 'wrong@example.com']);
        Sanctum::actingAs($wrongUser);

        $this->postJson("/api/v1/advisor/teams/invitations/{$inv->token}/accept")->assertForbidden();
    }

    // --- removeMember ---

    public function test_remove_member_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/advisor/teams/members/99')->assertUnauthorized();
    }

    public function test_remove_member_detaches_user_and_clears_current_team(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        $member->update(['current_team_id' => $team->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/advisor/teams/members/{$member->id}")->assertOk();

        $this->assertFalse($team->fresh()->hasMember($member));
        $this->assertNull($member->fresh()->current_team_id);
    }

    public function test_remove_member_forbidden_for_non_owner(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $other  = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id, $other->id]);
        Sanctum::actingAs($other);

        $this->deleteJson("/api/v1/advisor/teams/members/{$member->id}")->assertForbidden();
    }

    // --- destroy ---

    public function test_destroy_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/advisor/teams')->assertUnauthorized();
    }

    public function test_destroy_disbands_team_and_nullifies_team_references(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        $owner->update(['current_team_id' => $team->id]);
        $member->update(['current_team_id' => $team->id]);

        $agent = Agent::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);
        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/advisor/teams')->assertOk();

        $this->assertDatabaseMissing('teams', ['id' => $team->id]);
        $this->assertNull($owner->fresh()->current_team_id);
        $this->assertNull($member->fresh()->current_team_id);
        $this->assertNull($agent->fresh()->team_id);
    }

    public function test_destroy_forbidden_for_non_owner(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/advisor/teams')->assertForbidden();
    }

    // --- agent visibility ---

    public function test_team_agents_visible_to_all_team_members(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        $member->update(['current_team_id' => $team->id]);

        Agent::factory()->create(['user_id' => $owner->id, 'team_id' => $team->id]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/advisor/agents')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_personal_agents_not_visible_to_other_team_members(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $team   = Team::create(['owner_id' => $owner->id, 'name' => 'Acme']);
        $team->members()->attach([$owner->id, $member->id]);
        $member->update(['current_team_id' => $team->id]);

        // Personal agent (no team_id) — visible only to owner
        Agent::factory()->create(['user_id' => $owner->id, 'team_id' => null]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/advisor/agents')
            ->assertOk()
            ->assertJsonCount(0);
    }
}
