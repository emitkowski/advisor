<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\Learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $learning = Learning::factory()->create();

        $this->deleteJson("/api/v1/advisor/learnings/{$learning->id}")
            ->assertUnauthorized();
    }

    public function test_deletes_own_learning(): void
    {
        $user     = User::factory()->create();
        $learning = Learning::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/learnings/{$learning->id}")
            ->assertOk();

        $this->assertDatabaseMissing('learnings', ['id' => $learning->id]);
    }

    public function test_cannot_delete_another_users_learning(): void
    {
        $user     = User::factory()->create();
        $learning = Learning::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/advisor/learnings/{$learning->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('learnings', ['id' => $learning->id]);
    }

    public function test_returns_404_for_nonexistent_learning(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/advisor/learnings/99999')
            ->assertNotFound();
    }
}
