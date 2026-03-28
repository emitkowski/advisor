<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\PersonalityTrait;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdatePersonalityTraitTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->patchJson('/api/v1/advisor/personality-traits/directness', ['value' => 50])
            ->assertUnauthorized();
    }

    public function test_updates_trait_value(): void
    {
        $user  = User::factory()->create();
        $trait = PersonalityTrait::factory()->create(['user_id' => $user->id, 'trait' => 'directness', 'value' => 90]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/advisor/personality-traits/directness', ['value' => 50])
            ->assertOk()
            ->assertJsonPath('value', 50);

        $this->assertSame(50, $trait->fresh()->value);
    }

    public function test_value_is_required(): void
    {
        $user = User::factory()->create();
        PersonalityTrait::factory()->create(['user_id' => $user->id, 'trait' => 'directness']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/advisor/personality-traits/directness', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_value_must_be_between_0_and_100(): void
    {
        $user = User::factory()->create();
        PersonalityTrait::factory()->create(['user_id' => $user->id, 'trait' => 'directness']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/advisor/personality-traits/directness', ['value' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->patchJson('/api/v1/advisor/personality-traits/directness', ['value' => 101])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    public function test_cannot_update_another_users_trait(): void
    {
        $user  = User::factory()->create();
        PersonalityTrait::factory()->create(['trait' => 'directness', 'value' => 90]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/advisor/personality-traits/directness', ['value' => 10])
            ->assertNotFound();
    }

    public function test_returns_404_for_nonexistent_trait(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/advisor/personality-traits/nonexistent', ['value' => 50])
            ->assertNotFound();
    }
}
