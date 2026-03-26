<?php

namespace App\Jobs;

use App\Models\AdvisorSession;
use App\Models\Learning;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Signal;
use App\Services\AnthropicService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSessionLearning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(
        private int $sessionId
    ) {}

    public function handle(AnthropicService $claude): void
    {
        $session = AdvisorSession::with('user')->find($this->sessionId);

        if (!$session || !$session->thread) {
            return;
        }

        // Skip very short sessions — not enough signal
        if ($session->message_count < 4) {
            return;
        }

        $userId      = $session->user_id;
        $threadText  = $this->formatThreadForAnalysis($session->thread);

        try {
            $this->extractLearnings($claude, $userId, $session->id, $threadText);
            $this->extractProfiles($claude, $userId, $threadText);
            $this->extractProjects($claude, $userId, $session->id, $threadText);
            $this->inferImplicitRating($claude, $userId, $session->id, $threadText);
        } catch (\Throwable $e) {
            Log::error('ProcessSessionLearning failed', [
                'session_id' => $this->sessionId,
                'error'      => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Extract learnings: blind spots, patterns, follow-through, values, reactions.
     */
    private function extractLearnings(
        AnthropicService $claude,
        int $userId,
        int $sessionId,
        string $thread
    ): void {
        $system = "You are analyzing a conversation to extract durable learnings about how a specific person thinks.
Focus only on patterns that would be useful to remember in future conversations.
Be specific and observational, not flattering.";

        $prompt = "Analyze this conversation and extract learnings about the user.

Conversation:
{$thread}

Return JSON with this exact structure:
{
  \"learnings\": [
    {
      \"category\": \"blind_spot|pattern|follow_through|value|reaction|domain\",
      \"content\": \"specific observation in plain text\",
      \"confidence\": 0.0-1.0
    }
  ]
}

Categories:
- blind_spot: recurring thinking errors or gaps in reasoning
- pattern: how they characteristically approach problems
- follow_through: evidence about what they complete vs abandon
- value: what they explicitly say matters to them
- reaction: how they respond to pushback or hard truths
- domain: subject matter they know well or are weak in

Only include learnings you can support with specific evidence from the conversation.
If nothing worth noting, return {\"learnings\": []}";

        $result = $claude->completeJson($system, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        foreach ($result['learnings'] ?? [] as $item) {
            // Check if a similar learning already exists
            $existing = Learning::where('user_id', $userId)
                ->where('category', $item['category'])
                ->get()
                ->first(fn($l) => similar_text($l->content, $item['content']) > 70);

            if ($existing) {
                $existing->reinforce();
            } else {
                Learning::create([
                    'user_id'           => $userId,
                    'advisor_session_id' => $sessionId,
                    'category'          => $item['category'],
                    'content'           => $item['content'],
                    'confidence'        => $item['confidence'] ?? 0.6,
                    'last_seen_at'      => now(),
                ]);
            }
        }
    }

    /**
     * Extract or update profile observations.
     */
    private function extractProfiles(
        AnthropicService $claude,
        int $userId,
        string $thread
    ): void {
        $system = "Extract durable profile facts about a person from a conversation.
Only extract things that are stable characteristics, not one-off comments.";

        $prompt = "From this conversation, extract key-value profile facts about the user.

Conversation:
{$thread}

Return JSON:
{
  \"observations\": [
    {\"key\": \"snake_case_key\", \"value\": \"observation\", \"confidence\": 0.0-1.0}
  ]
}

Good keys: communication_style, risk_tolerance, technical_depth, decision_speed,
           overconfidence_tendency, research_habits, idea_validation_approach

If nothing clear, return {\"observations\": []}";

        $result = $claude->completeJson($system, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        foreach ($result['observations'] ?? [] as $obs) {
            Profile::observe($userId, $obs['key'], $obs['value'], $obs['confidence'] ?? 0.5);
        }
    }

    /**
     * Extract project mentions and update project tracking.
     */
    private function extractProjects(
        AnthropicService $claude,
        int $userId,
        int $sessionId,
        string $thread
    ): void {
        $system = "Extract mentions of projects, ideas, or ventures from a conversation.";

        $prompt = "Identify any projects, ideas, or ventures mentioned in this conversation.

Conversation:
{$thread}

Return JSON:
{
  \"projects\": [
    {
      \"name\": \"project name\",
      \"status\": \"active|abandoned|completed|paused|unclear\",
      \"description\": \"brief description or null\",
      \"notes\": \"any advisor observations about this project or null\"
    }
  ]
}

If no projects mentioned, return {\"projects\": []}";

        $result = $claude->completeJson($system, [
            ['role' => 'user', 'content' => $prompt],
        ]);

        foreach ($result['projects'] ?? [] as $item) {
            $project = Project::where('user_id', $userId)
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($item['name']) . '%'])
                ->first();

            if ($project) {
                $project->update([
                    'status'       => $item['status'] ?? $project->status,
                    'notes'        => $item['notes'] ?? $project->notes,
                    'last_seen_at' => now(),
                ]);
                $project->recordMention($sessionId);
            } else {
                Project::create([
                    'user_id'      => $userId,
                    'name'         => $item['name'],
                    'status'       => $item['status'] ?? 'unclear',
                    'description'  => $item['description'] ?? null,
                    'notes'        => $item['notes'] ?? null,
                    'mentions'     => [$sessionId],
                    'first_seen_at' => now(),
                    'last_seen_at'  => now(),
                ]);
            }
        }
    }

    /**
     * If no explicit rating was given, infer one from the conversation tone.
     */
    private function inferImplicitRating(
        AnthropicService $claude,
        int $userId,
        int $sessionId,
        string $thread
    ): void {
        // Skip if explicit rating already exists for this session
        $hasExplicit = Signal::where('advisor_session_id', $sessionId)
            ->where('type', 'explicit')
            ->exists();

        if ($hasExplicit) {
            return;
        }

        $system = "Analyze the user's satisfaction in a conversation. Return only JSON.";

        $prompt = "Based on the user's messages in this conversation, estimate their satisfaction level.

Conversation:
{$thread}

Return JSON:
{
  \"sentiment\": 0.0-1.0,
  \"rating\": 1.0-10.0,
  \"reasoning\": \"one sentence\"
}

0.0 = very frustrated/unhappy, 1.0 = very satisfied/engaged
Only analyze the USER messages, not the assistant messages.";

        try {
            $result = $claude->completeJson($system, [
                ['role' => 'user', 'content' => $prompt],
            ]);

            Signal::create([
                'user_id'           => $userId,
                'advisor_session_id' => $sessionId,
                'rating'            => $result['rating'] ?? 5.0,
                'type'              => 'implicit',
                'sentiment'         => $result['sentiment'] ?? 0.5,
                'context'           => $result['reasoning'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Non-critical — log and continue
            Log::warning('Implicit rating inference failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Format thread array into readable text for analysis.
     */
    private function formatThreadForAnalysis(array $thread): string
    {
        return collect($thread)
            ->map(fn($msg) => strtoupper($msg['role']) . ': ' . $msg['content'])
            ->join("\n\n");
    }
}
