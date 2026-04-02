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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
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
        $session = AdvisorSession::with(['participants:id,name'])->findOrFail($sessionId);

        if (!$session->isAccessibleBy(Auth::id())) {
            abort(404);
        }

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
            $session = AdvisorSession::with('agent')
                ->lockForUpdate()
                ->findOrFail($sessionId);

            if (!$session->isAccessibleBy(Auth::id())) {
                abort(404);
            }

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

        // Participants: load for system prompt context
        $participants = $session->participants()
            ->where('users.id', '!=', $session->user_id)
            ->get(['users.id', 'users.name'])
            ->toArray();

        // Prefix message with sender name so the agent knows who's speaking in joint sessions
        $isOwner   = Auth::id() === $session->user_id;
        $apiContent = (!$isOwner && count($participants) > 0)
            ? '[' . Auth::user()->name . ']: ' . $userMessage
            : $userMessage;

        // Build messages array for API — include new user message but don't persist yet
        $messages = array_merge($session->getApiMessages(), [
            ['role' => 'user', 'content' => $apiContent],
        ]);

        // Build system prompt anchored to session owner's memory context
        $systemPrompt = (new SystemPromptBuilder($session->user_id, $session->agent, $participants))->build();

        $senderId   = Auth::id();
        $senderName = Auth::user()->name;

        // Clear the Redis stream so late-joining participants always read the current response
        $streamKey = "session:{$session->id}:stream";
        Redis::del($streamKey);

        return response()->stream(function () use ($session, $systemPrompt, $messages, $userMessage, $explicitRating, $senderId, $senderName, $streamKey) {
            $fullResponse = '';

            try {
                $gen = $this->claude->stream($systemPrompt, $messages);

                foreach ($gen as $event) {
                    if ($event['type'] === 'text') {
                        $fullResponse .= $event['text'];
                        $payload = json_encode(['text' => $event['text']]);
                        echo "data: {$payload}\n\n";
                        Redis::xadd($streamKey, '*', ['d' => $payload]);
                    } elseif ($event['type'] === 'search_start') {
                        $payload = json_encode(['searching' => true]);
                        echo "data: {$payload}\n\n";
                        Redis::xadd($streamKey, '*', ['d' => $payload]);
                    } elseif ($event['type'] === 'search_end') {
                        $payload = json_encode(['searching' => false]);
                        echo "data: {$payload}\n\n";
                        Redis::xadd($streamKey, '*', ['d' => $payload]);
                    }
                    ob_flush();
                    flush();
                }

                $usage = $gen->getReturn() ?? [];

                // Only persist messages and signals after successful completion
                $session->addMessage('user', $userMessage, $senderId, $senderName);
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
                $donePayload = json_encode([
                    'done'          => true,
                    'input_tokens'  => $usage['input_tokens'] ?? 0,
                    'output_tokens' => $usage['output_tokens'] ?? 0,
                ]);
                echo "data: {$donePayload}\n\n";
                Redis::xadd($streamKey, '*', ['d' => $donePayload]);
                Redis::expire($streamKey, 300);
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
                $errPayload = json_encode(['error' => $errorMessage]);
                echo "data: {$errPayload}\n\n";
                Redis::xadd($streamKey, '*', ['d' => $errPayload]);
                Redis::expire($streamKey, 60);
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
     * Generate a share token for a session, making it publicly viewable.
     */
    public function share(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())->findOrFail($sessionId);

        if (!$session->share_token) {
            $session->update(['share_token' => Str::random(32)]);
        }

        return response()->json([
            'share_url' => route('advisor.shared', $session->share_token),
        ]);
    }

    /**
     * Revoke the share token for a session.
     */
    public function unshare(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())->findOrFail($sessionId);

        $session->update(['share_token' => null]);

        return response()->json(['message' => 'Share link revoked.']);
    }

    /**
     * Generate a join link for an active session (owner only).
     */
    public function generateJoinLink(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())->findOrFail($sessionId);

        if (!$session->isActive()) {
            return response()->json(['message' => 'Cannot generate a join link for a closed session.'], 422);
        }

        if (!$session->join_token) {
            $session->update(['join_token' => Str::random(32)]);
        }

        return response()->json([
            'join_url' => route('advisor.join', $session->join_token),
        ]);
    }

    /**
     * Revoke the join link for a session (owner only).
     * Existing participants remain; this only prevents new joins.
     */
    public function revokeJoinLink(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::where('user_id', Auth::id())->findOrFail($sessionId);

        $session->update(['join_token' => null]);

        return response()->json(['message' => 'Join link revoked.']);
    }

    /**
     * Leave a session as a participant. Messages remain; only the pivot row is removed.
     */
    public function leaveSession(int $sessionId): JsonResponse
    {
        $session = AdvisorSession::findOrFail($sessionId);

        // Owner cannot leave their own session
        if ($session->user_id === Auth::id()) {
            abort(422, 'You cannot leave a session you own.');
        }

        if (!$session->isAccessibleBy(Auth::id())) {
            abort(404);
        }

        $session->participants()->detach(Auth::id());

        return response()->json(['message' => 'You have left the session.']);
    }

    /**
     * SSE endpoint for participants to receive the live AI stream in real time.
     * Reads from a Redis stream that the owner's message() endpoint publishes to.
     */
    public function sessionStream(int $sessionId): StreamedResponse
    {
        $session = AdvisorSession::findOrFail($sessionId);

        if (!$session->isAccessibleBy(Auth::id())) {
            abort(404);
        }

        return response()->stream(function () use ($sessionId) {
            $streamKey = "session:{$sessionId}:stream";
            $lastId    = '0-0';
            $deadline  = time() + 120; // hold connection for up to 2 minutes

            while (time() < $deadline && !connection_aborted()) {
                $results = Redis::xread([$streamKey => $lastId], 50, 1000);

                if (empty($results)) {
                    // No data yet — send a keepalive event so the connection stays open
                    // and proxies reset their read timeout
                    echo "data: {\"ping\":true}\n\n";
                    ob_flush();
                    flush();
                    continue;
                }

                foreach (array_values($results)[0] as $entryId => $fields) {
                    $lastId  = $entryId;
                    $payload = $fields['d'] ?? '';
                    echo "data: {$payload}\n\n";
                    ob_flush();
                    flush();

                    $data = json_decode($payload, true);
                    if (($data['done'] ?? false) || isset($data['error'])) {
                        return;
                    }
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
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
