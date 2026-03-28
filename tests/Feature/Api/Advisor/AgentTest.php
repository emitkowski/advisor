<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'                   => 'My Custom Agent',
            'description'            => 'A focused advisor for product decisions.',
            'system_prompt_preamble' => 'You are a product advisor. Be concise.',
            'personality'            => [
                ['trait' => 'directness', 'value' => 80, 'description' => 'Clear and direct.'],
                ['trait' => 'skepticism',  'value' => 70, 'description' => 'Healthy skepticism.'],
            ],
        ], $overrides);
    }

    // --- index ---

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/advisor/agents')->assertUnauthorized();
    }

    public function test_index_returns_users_agents(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        Agent::factory()->count(3)->create(['user_id' => $user->id]);
        Agent::factory()->count(2)->create(['user_id' => $other->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/advisor/agents')
            ->assertOk()
            ->assertJsonCount(3);
    }

    // --- store ---

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/v1/advisor/agents', $this->validPayload())->assertUnauthorized();
    }

    public function test_store_creates_agent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/advisor/agents', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('name', 'My Custom Agent')
            ->assertJsonPath('is_preset', false);

        $this->assertDatabaseHas('agents', ['user_id' => $user->id, 'name' => 'My Custom Agent']);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/advisor/agents', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'description', 'system_prompt_preamble', 'personality']);
    }

    public function test_store_validates_personality_trait_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->validPayload(['personality' => [['trait' => 'directness']]]);
        $this->postJson('/api/v1/advisor/agents', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personality.0.value', 'personality.0.description']);
    }

    // --- update ---

    public function test_update_requires_authentication(): void
    {
        $agent = Agent::factory()->create();
        $this->patchJson("/api/v1/advisor/agents/{$agent->id}", ['name' => 'New'])->assertUnauthorized();
    }

    public function test_update_modifies_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/agents/{$agent->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('name', 'Renamed');

        $this->assertSame('Renamed', $agent->fresh()->name);
    }

    public function test_update_cannot_modify_another_users_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/advisor/agents/{$agent->id}", ['name' => 'Hijacked'])->assertNotFound();
    }

    // --- destroy ---

    public function test_destroy_requires_authentication(): void
    {
        $agent = Agent::factory()->create();
        $this->deleteJson("/api/v1/advisor/agents/{$agent->id}")->assertUnauthorized();
    }

    public function test_destroy_deletes_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/agents/{$agent->id}")->assertOk();
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);
    }

    public function test_destroy_cannot_delete_another_users_agent(): void
    {
        $user  = User::factory()->create();
        $agent = Agent::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/agents/{$agent->id}")->assertNotFound();
        $this->assertDatabaseHas('agents', ['id' => $agent->id]);
    }

    // --- seed presets ---

    public function test_seed_defaults_creates_five_preset_agents(): void
    {
        $user = User::factory()->create();
        Agent::seedDefaults($user->id);

        $this->assertSame(5, Agent::where('user_id', $user->id)->count());
        $this->assertSame(5, Agent::where('user_id', $user->id)->where('is_preset', true)->count());
    }

    public function test_seed_defaults_is_idempotent(): void
    {
        $user = User::factory()->create();
        Agent::seedDefaults($user->id);
        Agent::seedDefaults($user->id);

        $this->assertSame(5, Agent::where('user_id', $user->id)->count());
    }
}
