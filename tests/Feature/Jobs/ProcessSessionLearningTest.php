<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessSessionLearning;
use App\Models\AdvisorSession;
use App\Models\Learning;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Signal;
use App\Models\User;
use App\Services\AnthropicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessSessionLearningTest extends TestCase
{
    use RefreshDatabase;

    private function mockClaude(array $learnings = [], array $observations = [], array $projects = [], ?array $rating = null, string $title = 'Test Session Title'): void
    {
        $this->mock(AnthropicService::class, function (MockInterface $mock) use ($learnings, $observations, $projects, $rating, $title) {
            $mock->shouldReceive('completeJson')
                ->andReturnValues([
                    ['title'         => $title],
                    ['learnings'     => $learnings],
                    ['observations'  => $observations],
                    ['projects'      => $projects],
                    $rating ?? ['sentiment' => 0.8, 'rating' => 8.0, 'reasoning' => 'Engaged user'],
                ]);
        });
    }

    private function sessionWithMessages(int $count = 4): AdvisorSession
    {
        return AdvisorSession::factory()->withMessages($count)->create();
    }

    public function test_skips_session_with_fewer_than_four_messages(): void
    {
        $session = AdvisorSession::factory()->withMessages(2)->create();

        $this->mock(AnthropicService::class, fn (MockInterface $mock) =>
            $mock->shouldNotReceive('completeJson')
        );

        ProcessSessionLearning::dispatchSync($session->id);
    }

    public function test_skips_session_with_no_thread(): void
    {
        $session = AdvisorSession::factory()->create(['thread' => null]);

        $this->mock(AnthropicService::class, fn (MockInterface $mock) =>
            $mock->shouldNotReceive('completeJson')
        );

        ProcessSessionLearning::dispatchSync($session->id);
    }

    public function test_skips_nonexistent_session(): void
    {
        $this->mock(AnthropicService::class, fn (MockInterface $mock) =>
            $mock->shouldNotReceive('completeJson')
        );

        ProcessSessionLearning::dispatchSync(99999);
    }

    public function test_creates_learnings_from_session(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(learnings: [
            ['category' => 'blind_spot', 'content' => 'Skips validation steps', 'confidence' => 0.8],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('learnings', [
            'user_id'  => $session->user_id,
            'category' => 'blind_spot',
            'content'  => 'Skips validation steps',
        ]);
    }

    public function test_reinforces_existing_similar_learning(): void
    {
        $session  = $this->sessionWithMessages();
        $learning = Learning::factory()->create([
            'user_id'            => $session->user_id,
            'category'           => 'pattern',
            'content'            => 'Tends to overbuild solutions',
            'confidence'         => 0.7,
            'reinforcement_count' => 1,
        ]);

        $this->mockClaude(learnings: [
            ['category' => 'pattern', 'content' => 'Tends to overbuild solutions', 'confidence' => 0.75],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertSame(2, $learning->fresh()->reinforcement_count);
        $this->assertDatabaseCount('learnings', 1);
    }

    public function test_creates_profile_observations(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(observations: [
            ['key' => 'risk_tolerance', 'value' => 'Low', 'confidence' => 0.7],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $session->user_id,
            'key'     => 'risk_tolerance',
            'value'   => 'Low',
        ]);
    }

    public function test_updates_existing_profile_observation(): void
    {
        $session = $this->sessionWithMessages();
        Profile::factory()->create([
            'user_id'           => $session->user_id,
            'key'               => 'risk_tolerance',
            'value'             => 'Medium',
            'observation_count' => 1,
        ]);

        $this->mockClaude(observations: [
            ['key' => 'risk_tolerance', 'value' => 'Low', 'confidence' => 0.7],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('profiles', ['key' => 'risk_tolerance', 'value' => 'Low']);
        $this->assertDatabaseCount('profiles', 1);
    }

    public function test_creates_new_project(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(projects: [
            ['name' => 'My SaaS App', 'status' => 'active', 'description' => 'A new idea', 'notes' => null],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('projects', [
            'user_id' => $session->user_id,
            'name'    => 'My SaaS App',
            'status'  => 'active',
        ]);
    }

    public function test_updates_existing_project(): void
    {
        $session = $this->sessionWithMessages();
        $project = Project::factory()->create([
            'user_id' => $session->user_id,
            'name'    => 'My SaaS App',
            'status'  => 'active',
        ]);

        $this->mockClaude(projects: [
            ['name' => 'My SaaS App', 'status' => 'abandoned', 'description' => null, 'notes' => 'Gave up after week 2'],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertSame('abandoned', $project->fresh()->status);
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_creates_implicit_signal_when_no_explicit_exists(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(rating: ['sentiment' => 0.9, 'rating' => 9.0, 'reasoning' => 'Very engaged']);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('signals', [
            'user_id'            => $session->user_id,
            'advisor_session_id' => $session->id,
            'type'               => 'implicit',
            'rating'             => 9.0,
        ]);
    }

    public function test_skips_implicit_signal_when_any_signal_already_exists(): void
    {
        $session = $this->sessionWithMessages();
        Signal::factory()->explicit()->create([
            'user_id'            => $session->user_id,
            'advisor_session_id' => $session->id,
        ]);

        $this->mockClaude();

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('signals', 1);
        $this->assertDatabaseMissing('signals', ['type' => 'implicit']);
    }

    public function test_skips_implicit_signal_when_implicit_already_exists(): void
    {
        $session = $this->sessionWithMessages();
        Signal::factory()->create([
            'user_id'            => $session->user_id,
            'advisor_session_id' => $session->id,
            'type'               => 'implicit',
        ]);

        $this->mockClaude();

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('signals', 1);
    }

    public function test_generates_title_for_untitled_session(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(title: 'Evaluating the SaaS Idea');

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertSame('Evaluating the SaaS Idea', $session->fresh()->title);
    }

    public function test_project_name_uses_exact_match_not_substring(): void
    {
        $session = $this->sessionWithMessages();
        $project = Project::factory()->create([
            'user_id' => $session->user_id,
            'name'    => 'Rapid Application',
            'status'  => 'active',
        ]);

        // "API" should NOT match "Rapid Application" via substring
        $this->mockClaude(projects: [
            ['name' => 'API', 'status' => 'active', 'description' => 'A new API project', 'notes' => null],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertSame('active', $project->fresh()->status);
        $this->assertDatabaseCount('projects', 2);
        $this->assertDatabaseHas('projects', ['name' => 'API']);
    }

    public function test_does_not_overwrite_existing_title(): void
    {
        $session = AdvisorSession::factory()->withMessages()->create(['title' => 'Already Set']);

        $this->mock(AnthropicService::class, function (MockInterface $mock) {
            // title step should be skipped — completeJson called only 4 times, not 5
            $mock->shouldReceive('completeJson')
                ->times(4)
                ->andReturnValues([
                    ['learnings'    => []],
                    ['observations' => []],
                    ['projects'     => []],
                    ['sentiment' => 0.8, 'rating' => 8.0, 'reasoning' => 'Good'],
                ]);
        });

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertSame('Already Set', $session->fresh()->title);
    }

    // --- JSON shape validation ---

    public function test_skips_learning_missing_content(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(learnings: [
            ['category' => 'blind_spot'], // missing content
            ['category' => 'pattern', 'content' => 'Valid learning', 'confidence' => 0.7],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('learnings', 1);
        $this->assertDatabaseHas('learnings', ['content' => 'Valid learning']);
    }

    public function test_skips_learning_with_invalid_category(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(learnings: [
            ['category' => 'made_up_category', 'content' => 'Some content', 'confidence' => 0.7],
            ['category' => 'value', 'content' => 'Real learning', 'confidence' => 0.7],
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('learnings', 1);
        $this->assertDatabaseHas('learnings', ['content' => 'Real learning']);
    }

    public function test_skips_learning_missing_category(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(learnings: [
            ['content' => 'Content without category', 'confidence' => 0.7], // missing category
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('learnings', 0);
    }

    public function test_skips_observation_missing_key_or_value(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(observations: [
            ['key' => 'risk_tolerance'],                           // missing value
            ['value' => 'High'],                                   // missing key
            ['key' => 'decision_speed', 'value' => 'Fast', 'confidence' => 0.7], // valid
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('profiles', 1);
        $this->assertDatabaseHas('profiles', ['key' => 'decision_speed']);
    }

    public function test_skips_project_missing_name(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(projects: [
            ['status' => 'active', 'description' => 'No name here'], // missing name
            ['name' => 'Valid Project', 'status' => 'active'],       // valid
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['name' => 'Valid Project']);
    }

    public function test_normalises_invalid_project_status_to_unclear(): void
    {
        $session = $this->sessionWithMessages();
        $this->mockClaude(projects: [
            ['name' => 'My App', 'status' => 'in_progress'], // not a valid status
        ]);

        ProcessSessionLearning::dispatchSync($session->id);

        $this->assertDatabaseHas('projects', ['name' => 'My App', 'status' => 'unclear']);
    }
}
