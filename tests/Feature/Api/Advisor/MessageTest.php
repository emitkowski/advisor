<?php

namespace Tests\Feature\Api\Advisor;

use App\Models\AdvisorSession;
use App\Models\Signal;
use App\Models\User;
use App\Services\AnthropicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private function mockStream(array $chunks = ['Hello ', 'world'], array $usage = ['input_tokens' => 10, 'output_tokens' => 5]): void
    {
        $this->mock(AnthropicService::class, function (MockInterface $mock) use ($chunks, $usage) {
            $mock->shouldReceive('stream')
                ->andReturnUsing(function () use ($chunks, $usage) {
                    return (function () use ($chunks, $usage) {
                        foreach ($chunks as $chunk) {
                            yield $chunk;
                        }
                        return $usage;
                    })();
                });
        });
    }

    public function test_requires_authentication(): void
    {
        $session = AdvisorSession::factory()->create();

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_content_is_required(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_content_must_not_exceed_maximum_length(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content' => str_repeat('a', 10001),
        ])->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_closed_session_returns_unprocessable(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->closed()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi'])
            ->assertUnprocessable();
    }

    public function test_blocks_other_users_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi'])
            ->assertNotFound();
    }

    public function test_streams_response_with_sse_headers(): void
    {
        $this->mockStream();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hello']);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
    }

    public function test_streams_content_chunks_in_sse_format(): void
    {
        $this->mockStream(['chunk one ', 'chunk two']);
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hello']);

        $body = $response->streamedContent();
        $this->assertStringContainsString('data: {"text":"chunk one "}', $body);
        $this->assertStringContainsString('data: {"text":"chunk two"}', $body);
        $this->assertStringContainsString('"done":true', $body);
    }

    public function test_adds_user_message_to_thread(): void
    {
        $this->mockStream();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'test message']);
        $response->streamedContent(); // trigger callback execution

        $thread = $session->fresh()->thread;
        $this->assertSame('user', $thread[0]['role']);
        $this->assertSame('test message', $thread[0]['content']);
    }

    public function test_adds_assistant_response_to_thread(): void
    {
        $this->mockStream(['Hello ', 'world']);
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi']);
        $response->streamedContent(); // trigger callback execution

        $thread = $session->fresh()->thread;
        $this->assertSame('assistant', $thread[1]['role']);
        $this->assertSame('Hello world', $thread[1]['content']);
    }

    public function test_detects_explicit_rating_and_creates_signal(): void
    {
        $this->mockStream();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => '8/10 great advice']);
        $response->streamedContent();

        $this->assertDatabaseHas('signals', [
            'user_id'            => $user->id,
            'advisor_session_id' => $session->id,
            'type'               => 'explicit',
            'rating'             => 8.0,
        ]);
    }

    public function test_message_without_rating_does_not_create_signal(): void
    {
        $this->mockStream();
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'just a message']);
        $response->streamedContent();

        $this->assertDatabaseEmpty('signals');
    }

    public function test_duplicate_idempotency_key_is_rejected(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mockStream();
        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content'         => 'hello',
            'idempotency_key' => 'test-key-abc',
        ]);
        $response->streamedContent();

        // Second request with same key should be rejected
        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content'         => 'hello',
            'idempotency_key' => 'test-key-abc',
        ])->assertStatus(409);
    }

    public function test_different_idempotency_keys_are_accepted(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mockStream(['Hi']);
        $r1 = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content'         => 'first',
            'idempotency_key' => 'key-1',
        ]);
        $r1->streamedContent();

        $this->mockStream(['There']);
        $r2 = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content'         => 'second',
            'idempotency_key' => 'key-2',
        ]);
        $r2->assertOk();
    }

    public function test_streaming_failure_leaves_thread_unchanged(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mock(AnthropicService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stream')
                ->andThrow(new \RuntimeException('API failure'));
        });

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi']);
        $response->streamedContent();

        $this->assertNull($session->fresh()->thread);
    }

    public function test_streaming_failure_preserves_existing_thread_messages(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->withMessages(2)->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mock(AnthropicService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stream')
                ->andThrow(new \RuntimeException('API failure'));
        });

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi']);
        $response->streamedContent();

        $this->assertCount(2, $session->fresh()->thread);
    }

    public function test_message_count_increments_by_two_after_successful_exchange(): void
    {
        $this->mockStream(['Hello']);
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi']);
        $response->streamedContent();

        $this->assertSame(2, $session->fresh()->message_count);
    }

    public function test_token_usage_is_accumulated_on_session(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mockStream(['Hello'], ['input_tokens' => 100, 'output_tokens' => 50]);

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi']);
        $response->streamedContent();

        $fresh = $session->fresh();
        $this->assertSame(100, $fresh->input_tokens);
        $this->assertSame(50, $fresh->output_tokens);
    }

    public function test_token_usage_accumulates_across_multiple_messages(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $call = 0;
        $this->mock(AnthropicService::class, function (MockInterface $mock) use (&$call) {
            $mock->shouldReceive('stream')
                ->andReturnUsing(function () use (&$call) {
                    $usage = ++$call === 1
                        ? ['input_tokens' => 100, 'output_tokens' => 50]
                        : ['input_tokens' => 200, 'output_tokens' => 80];
                    return (function () use ($usage) {
                        yield 'response';
                        return $usage;
                    })();
                });
        });

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'first'])->streamedContent();
        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'second'])->streamedContent();

        $fresh = $session->fresh();
        $this->assertSame(300, $fresh->input_tokens);
        $this->assertSame(130, $fresh->output_tokens);
    }

    public function test_done_event_includes_token_counts(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mockStream(['Hi'], ['input_tokens' => 42, 'output_tokens' => 17]);

        $body = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi'])
            ->streamedContent();

        $this->assertStringContainsString('"input_tokens":42', $body);
        $this->assertStringContainsString('"output_tokens":17', $body);
    }

    public function test_streaming_failure_does_not_accumulate_tokens(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mock(AnthropicService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stream')->andThrow(new \RuntimeException('API failure'));
        });

        $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", ['content' => 'hi'])
            ->streamedContent();

        $fresh = $session->fresh();
        $this->assertSame(0, $fresh->input_tokens);
        $this->assertSame(0, $fresh->output_tokens);
    }

    public function test_streaming_failure_does_not_create_signal(): void
    {
        $user    = User::factory()->create();
        $session = AdvisorSession::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->mock(AnthropicService::class, function (MockInterface $mock) {
            $mock->shouldReceive('stream')
                ->andThrow(new \RuntimeException('API failure'));
        });

        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content' => '8/10 this was great',
        ]);
        $response->streamedContent();

        $this->assertDatabaseEmpty('signals');
    }

    public function test_idempotency_key_boundary_rotates_oldest_key(): void
    {
        $user         = User::factory()->create();
        $existingKeys = array_map(fn ($i) => "old-key-{$i}", range(1, 50));
        $session      = AdvisorSession::factory()->create([
            'user_id' => $user->id,
            'meta'    => ['processed_keys' => $existingKeys],
        ]);
        Sanctum::actingAs($user);

        $this->mockStream(['hi']);
        $response = $this->postJson("/api/v1/advisor/sessions/{$session->id}/message", [
            'content'         => 'hello',
            'idempotency_key' => 'new-key',
        ]);
        $response->streamedContent();
        $response->assertOk();

        $meta = $session->fresh()->meta;
        $this->assertCount(50, $meta['processed_keys']);
        $this->assertNotContains('old-key-1', $meta['processed_keys']);
        $this->assertContains('new-key', $meta['processed_keys']);
    }
}
