<?php

namespace Tests\Feature\Services;

use App\Services\AnthropicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicStreamCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['advisor.anthropic_api_key' => 'test-key']);
    }

    private function fakeSseResponse(string $body): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($body, 200)]);
    }

    private function consumeStream(AnthropicService $service): array
    {
        $gen    = $service->stream('system', [['role' => 'user', 'content' => 'test']]);
        $chunks = [];
        foreach ($gen as $chunk) {
            $chunks[] = $chunk;
        }
        return ['chunks' => $chunks, 'usage' => $gen->getReturn()];
    }

    public function test_stream_yields_text_and_returns_tokens_when_message_stop_received(): void
    {
        $this->fakeSseResponse(implode('', [
            "data: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":10}}}\n\n",
            "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\"Hello\"}}\n\n",
            "data: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":5}}\n\n",
            "data: {\"type\":\"message_stop\"}\n\n",
        ]));

        $result = $this->consumeStream(new AnthropicService());

        $this->assertSame(['Hello'], $result['chunks']);
        $this->assertSame(10, $result['usage']['input_tokens']);
        $this->assertSame(5, $result['usage']['output_tokens']);
    }

    public function test_stream_throws_when_eof_without_completion_event(): void
    {
        $this->fakeSseResponse(implode('', [
            "data: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":10}}}\n\n",
            "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\"Partial response\"}}\n\n",
            // No message_stop — stream just ends at EOF
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream ended without a completion event');

        $gen = (new AnthropicService())->stream('system', [['role' => 'user', 'content' => 'test']]);
        foreach ($gen as $_) {}
        $gen->getReturn();
    }

    public function test_stream_succeeds_with_done_marker_after_message_stop(): void
    {
        $this->fakeSseResponse(implode('', [
            "data: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":5}}}\n\n",
            "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\"Hi\"}}\n\n",
            "data: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":3}}\n\n",
            "data: {\"type\":\"message_stop\"}\n\n",
            "data: [DONE]\n\n",
        ]));

        $result = $this->consumeStream(new AnthropicService());

        $this->assertSame(['Hi'], $result['chunks']);
        $this->assertSame(5, $result['usage']['input_tokens']);
        $this->assertSame(3, $result['usage']['output_tokens']);
    }

    public function test_stream_exits_cleanly_on_done_marker_without_prior_message_stop(): void
    {
        // [DONE] alone should also count as completion (some API versions may omit message_stop)
        $this->fakeSseResponse(implode('', [
            "data: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":5}}}\n\n",
            "data: {\"type\":\"content_block_delta\",\"delta\":{\"text\":\"Hi\"}}\n\n",
            "data: [DONE]\n\n",
        ]));

        $result = $this->consumeStream(new AnthropicService());

        $this->assertSame(['Hi'], $result['chunks']);
    }
}
