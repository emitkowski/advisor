<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UserFacingException;
use App\Jobs\ProcessSessionLearning;
use App\Models\Agent;
use App\Models\AdvisorSession;
use App\Models\Learning;
use App\Models\PersonalityTrait;
use App\Models\Profile;
use App\Models\Signal;
use App\Services\AnthropicService;
use App\Services\SystemPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private AnthropicService $claude
    ) {}

    /**
     * List all sessions for the current user.
     */
    public function index(): JsonResponse
    {
        $sessions = AdvisorSession::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->select(['id', 'title', 'message_count', 'input_tokens', 'output_tokens', 'avg_rating', 'started_at', 'ended_at', 'created_at'])
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Create a new session.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'agent_id' => 'nullable|integer',
        ]);

        PersonalityTrait::seedDefaults(Auth::id());
        Agent::seedDefaults(Auth::id());

        $agentId = null;
        if ($request->filled('agent_id')) {
            $agent   = Agent::where('user_id', Auth::id())->findOrFail($request->input('agent_id'));
            $agentId = $agent->id;
        }

        $session = AdvisorSession::create([
            'user_id'    => Auth::id(),
            'agent_id'   => $agentId,
            'started_at' => now(),
        ]);

        return response()->json($session->load('agent'), 201);
    }

    /**
     * Get a session with its thread.
     */
    public function show(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        return response()->json($session);
    }

    /**
     * Update session properties (currently: title).
     */
    public function update(Request $request, int $sessionId): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:120',
        ]);

        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        $session->update(['title' => $request->input('title')]);

        return response()->json(['title' => $session->title]);
    }

    /**
     * Send a message — returns a streaming SSE response.
     */
    public function message(Request $request, int $sessionId): StreamedResponse
    {
        $request->validate([
            'content'         => 'required|string|max:10000',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        $idempotencyKey = $request->input('idempotency_key');
        $userMessage    = $request->input('content');

        // Atomically validate session state and record idempotency key
        $session = DB::transaction(function () use ($sessionId, $idempotencyKey) {
            $session = AdvisorSession::where('user_id', Auth::id())
                ->with('agent')
                ->lockForUpdate()
                ->findOrFail($sessionId);

            if (!$session->isActive()) {
                abort(422, 'Session is closed. Start a new session.');
            }

            if ($idempotencyKey) {
                $processedKeys = $session->meta['processed_keys'] ?? [];
                if (in_array($idempotencyKey, $processedKeys)) {
                    abort(409, 'Duplicate request.');
                }
                $session->update([
                    'meta' => array_merge($session->meta ?? [], [
                        'processed_keys' => array_slice(
                            array_merge($processedKeys, [$idempotencyKey]),
                            -50 // keep last 50 keys to prevent unbounded growth
                        ),
                    ]),
                ]);
            }

            return $session;
        });

        // Detect explicit rating now, but persist it only after streaming succeeds
        $explicitRating = Signal::detectExplicitRating($userMessage);

        // Build messages array for API — include new user message but don't persist yet
        $messages = array_merge($session->getApiMessages(), [
            ['role' => 'user', 'content' => $userMessage],
        ]);

        // Build system prompt with all memory context
        $systemPrompt = (new SystemPromptBuilder(Auth::id(), $session->agent))->build();

        return response()->stream(function () use ($session, $systemPrompt, $messages, $userMessage, $explicitRating) {
            $fullResponse = '';

            try {
                $gen = $this->claude->stream($systemPrompt, $messages);

                foreach ($gen as $chunk) {
                    $fullResponse .= $chunk;

                    // Send SSE event
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }

                $usage = $gen->getReturn() ?? [];

                // Only persist messages and signals after successful completion
                $session->addMessage('user', $userMessage);
                $session->addMessage('assistant', $fullResponse);
                $session->accumulateTokens($usage['input_tokens'] ?? 0, $usage['output_tokens'] ?? 0);

                if ($explicitRating !== null) {
                    Signal::create([
                        'user_id'            => $session->user_id,
                        'advisor_session_id' => $session->id,
                        'rating'             => $explicitRating,
                        'type'               => 'explicit',
                        'context'            => 'User provided explicit rating',
                        'message_snippet'    => substr($userMessage, 0, 200),
                    ]);
                }

                // Signal stream complete, include per-exchange token counts for client display
                echo "data: " . json_encode([
                    'done'          => true,
                    'input_tokens'  => $usage['input_tokens'] ?? 0,
                    'output_tokens' => $usage['output_tokens'] ?? 0,
                ]) . "\n\n";
                ob_flush();
                flush();

            } catch (\Throwable $e) {
                Log::error('Streaming response failed', [
                    'session_id' => $session->id,
                    'error'      => $e->getMessage(),
                ]);
                $errorMessage = $e instanceof UserFacingException
                    ? $e->getMessage()
                    : 'An error occurred. Please try again.';
                echo "data: " . json_encode(['error' => $errorMessage]) . "\n\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ]);
    }

    /**
     * Record an explicit rating for a session response.
     */
    public function rate(Request $request, int $sessionId): JsonResponse
    {
        $request->validate([
            'rating'          => 'required|numeric|min:1|max:10',
            'context'         => 'nullable|string|max:500',
            'message_snippet' => 'nullable|string|max:200',
        ]);

        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        Signal::create([
            'user_id'            => $session->user_id,
            'advisor_session_id' => $session->id,
            'rating'             => $request->input('rating'),
            'type'               => 'explicit',
            'context'            => $request->input('context', 'User rated via UI'),
            'message_snippet'    => $request->input('message_snippet'),
        ]);

        return response()->json(['message' => 'Rating recorded.'], 201);
    }

    /**
     * Delete a session and its associated signals.
     */
    public function destroy(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        $session->delete();

        return response()->json(['message' => 'Session deleted.']);
    }

    /**
     * Close a session and trigger learning extraction.
     */
    public function close(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        if (!$session->isActive()) {
            return response()->json(['message' => 'Session already closed'], 422);
        }

        $session->close();

        // Dispatch async learning job
        ProcessSessionLearning::dispatch($session->id)
            ->onQueue('learning')
            ->delay(now()->addSeconds(5)); // Small delay to ensure thread is fully written

        return response()->json([
            'message'    => 'Session closed. Learning extraction queued.',
            'session_id' => $session->id,
            'avg_rating' => $session->avg_rating,
        ]);
    }

    /**
     * Update a personality trait value.
     */
    public function updateTrait(Request $request, string $traitName): JsonResponse
    {
        $request->validate([
            'value' => 'required|integer|min:0|max:100',
        ]);

        $trait = PersonalityTrait::where('user_id', Auth::id())
            ->where('trait', $traitName)
            ->firstOrFail();

        $trait->update(['value' => $request->input('value')]);

        return response()->json(['message' => 'Trait updated.', 'value' => $trait->value]);
    }

    /**
     * Delete a learning record.
     */
    public function deleteLearning(int $learningId): JsonResponse
    {
        $learning = Learning::where('user_id', Auth::id())
            ->findOrFail($learningId);

        $learning->delete();

        return response()->json(['message' => 'Learning deleted.']);
    }

    /**
     * Delete a profile observation.
     */
    public function deleteProfileObservation(int $profileId): JsonResponse
    {
        $profile = Profile::where('user_id', Auth::id())
            ->findOrFail($profileId);

        $profile->delete();

        return response()->json(['message' => 'Observation deleted.']);
    }

    /**
     * Get the current system prompt for debugging/transparency.
     */
    public function systemPrompt(): JsonResponse
    {
        $prompt = (new SystemPromptBuilder(Auth::id()))->build();

        return response()->json(['system_prompt' => $prompt]);
    }
}
