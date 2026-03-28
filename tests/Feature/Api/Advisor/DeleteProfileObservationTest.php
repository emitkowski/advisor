<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteProfileObservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $profile = Profile::factory()->create();

        $this->deleteJson("/api/v1/advisor/profile-observations/{$profile->id}")
            ->assertUnauthorized();
    }

    public function test_deletes_own_observation(): void
    {
        $user    = User::factory()->create();
        $profile = Profile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/profile-observations/{$profile->id}")
            ->assertOk();

        $this->assertDatabaseMissing('profiles', ['id' => $profile->id]);
    }

    public function test_cannot_delete_another_users_observation(): void
    {
        $user    = User::factory()->create();
        $profile = Profile::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/profile-observations/{$profile->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('profiles', ['id' => $profile->id]);
    }

    public function test_returns_404_for_nonexistent_observation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/advisor/profile-observations/99999')
            ->assertNotFound();
    }
}
