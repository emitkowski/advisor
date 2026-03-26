<?php

namespace App\Services;

use App\Models\AdvisorSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Generator;

class AnthropicService
{
    private string $apiKey;
    private string $model;
    private int    $maxTokens;
    private string $apiBase = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey    = config('advisor.anthropic_api_key');
        $this->model     = config('advisor.model', 'claude-sonnet-4-20250514');
        $this->maxTokens = config('advisor.max_tokens', 2048);
    }

    /**
     * Send a message and return the full response text.
     * Use for background jobs and non-streaming contexts.
     */
    public function complete(string $systemPrompt, array $messages): string
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(60)
            ->post("{$this->apiBase}/messages", [
                'model'      => $this->model,
                'max_tokens' => $this->maxTokens,
                'system'     => $systemPrompt,
                'messages'   => $messages,
            ]);

        if ($response->failed()) {
            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Anthropic API request failed: ' . $response->body());
        }

        $data = $response->json();

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Stream a response using Server-Sent Events.
     * Yields text chunks as they arrive.
     * Use this in your controller for real-time chat UI.
     */
    public function stream(string $systemPrompt, array $messages): Generator
    {
        $response = Http::withHeaders($this->headers())
            ->withOptions([
                'stream'  => true,
                'timeout' => 120,
            ])
            ->post("{$this->apiBase}/messages", [
                'model'      => $this->model,
                'max_tokens' => $this->maxTokens,
                'system'     => $systemPrompt,
                'messages'   => $messages,
                'stream'     => true,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Anthropic streaming request failed');
        }

        $buffer = '';

        foreach ($response->toPsrResponse()->getBody() as $chunk) {
            $buffer .= $chunk;
            $lines   = explode("\n", $buffer);
            $buffer  = array_pop($lines); // keep incomplete line in buffer

            foreach ($lines as $line) {
                $line = trim($line);

                if (str_starts_with($line, 'data: ')) {
                    $json = substr($line, 6);

                    if ($json === '[DONE]') {
                        return;
                    }

                    $event = json_decode($json, true);

                    if (
                        isset($event['type']) &&
                        $event['type'] === 'content_block_delta' &&
                        isset($event['delta']['text'])
                    ) {
                        yield $event['delta']['text'];
                    }
                }
            }
        }
    }

    /**
     * Complete with structured JSON output.
     * Used by the learning job to extract patterns.
     */
    public function completeJson(string $systemPrompt, array $messages): array
    {
        $jsonSystemPrompt = $systemPrompt . "\n\nIMPORTANT: Respond ONLY with valid JSON. No markdown, no preamble, no backticks.";

        $text = $this->complete($jsonSystemPrompt, $messages);

        // Strip any accidental markdown fences
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $data = json_decode(trim($text), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse JSON from Claude', ['response' => $text]);
            throw new \RuntimeException('Claude returned invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    private function headers(): array
    {
        return [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ];
    }
}
