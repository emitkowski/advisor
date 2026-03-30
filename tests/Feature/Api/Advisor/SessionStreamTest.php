<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SessionStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Clean up any Redis stream keys created during tests
        Redis::del('session:*:stream');
        parent::tearDown();
    }

    private function streamKey(int $sessionId): string
    {
        return "session:{$sessionId}:stream";
    }

    private function publishChunks(int $sessionId, array $payloads): void
    {
        $key = $this->streamKey($sessionId);
        Redis::del($key);
        foreach ($payloads as $payload) {
            Redis::xadd($key, '*', ['d' => json_encode($payload)]);
        }
    }

    public function test_unauthenticated_cannot_access_stream(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->getJson("/api/v1/advisor/sessions/{$session->id}/stream")->assertUnauthorized();
    }

    public function test_non_participant_gets_404(): void
    {
        $owner    = User::factory()->create();
        $stranger = User::factory()->create();
        $session  = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($stranger);

        $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->assertNotFound();
    }

    public function test_owner_can_access_stream(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['text' => 'Hello'],
            ['done' => true],
        ]);

        Sanctum::actingAs($owner);

        $response = $this->get("/api/v1/advisor/sessions/{$session->id}/stream");
        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_participant_can_access_stream(): void
    {
        $owner     = User::factory()->create();
        $joiner    = User::factory()->create();
        $session   = AdvisorSession::factory()->withParticipant($joiner)->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['text' => 'Hi'],
            ['done' => true],
        ]);

        Sanctum::actingAs($joiner);

        $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->assertOk();
    }

    public function test_stream_returns_sse_headers(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [['done' => true]]);

        Sanctum::actingAs($owner);

        $response = $this->get("/api/v1/advisor/sessions/{$session->id}/stream");

        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $response->assertHeader('Cache-Control', 'no-cache, private');
        $response->assertHeader('X-Accel-Buffering', 'no');
    }

    public function test_stream_emits_text_chunks(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['text' => 'Hello '],
            ['text' => 'world'],
            ['done' => true],
        ]);

        Sanctum::actingAs($owner);

        $body = $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->streamedContent();

        $this->assertStringContainsString('data: {"text":"Hello "}', $body);
        $this->assertStringContainsString('data: {"text":"world"}', $body);
    }

    public function test_stream_stops_at_done_event(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['text' => 'Before done'],
            ['done' => true, 'input_tokens' => 10, 'output_tokens' => 5],
            ['text' => 'After done — should not appear'],
        ]);

        Sanctum::actingAs($owner);

        $body = $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->streamedContent();

        $this->assertStringContainsString('Before done', $body);
        $this->assertStringNotContainsString('After done', $body);
    }

    public function test_stream_emits_done_with_token_counts(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['done' => true, 'input_tokens' => 42, 'output_tokens' => 17],
        ]);

        Sanctum::actingAs($owner);

        $body = $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->streamedContent();

        $this->assertStringContainsString('"done":true', $body);
        $this->assertStringContainsString('"input_tokens":42', $body);
        $this->assertStringContainsString('"output_tokens":17', $body);
    }

    public function test_stream_emits_searching_events(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['searching' => true],
            ['searching' => false],
            ['text' => 'Result'],
            ['done' => true],
        ]);

        Sanctum::actingAs($owner);

        $body = $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->streamedContent();

        $this->assertStringContainsString('"searching":true', $body);
        $this->assertStringContainsString('"searching":false', $body);
    }

    public function test_stream_stops_at_error_event(): void
    {
        $owner   = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $owner->id]);

        $this->publishChunks($session->id, [
            ['error' => 'Something went wrong'],
            ['text'  => 'Should not appear'],
        ]);

        Sanctum::actingAs($owner);

        $body = $this->get("/api/v1/advisor/sessions/{$session->id}/stream")->streamedContent();

        $this->assertStringContainsString('"error":"Something went wrong"', $body);
        $this->assertStringNotContainsString('Should not appear', $body);
    }
}
