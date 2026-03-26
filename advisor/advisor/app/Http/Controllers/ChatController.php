<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSessionLearning;
use App\Models\AdvisorSession;
use App\Models\PersonalityTrait;
use App\Models\Signal;
use App\Services\AnthropicService;
use App\Services\SystemPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
            ->select(['id', 'title', 'message_count', 'avg_rating', 'started_at', 'ended_at', 'created_at'])
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * Create a new session.
     */
    public function store(): JsonResponse
    {
        // Ensure user has personality traits seeded
        PersonalityTrait::seedDefaults(Auth::id());

        $session = AdvisorSession::create([
            'user_id'    => Auth::id(),
            'started_at' => now(),
        ]);

        return response()->json($session, 201);
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
     * Send a message — returns a streaming SSE response.
     */
    public function message(Request $request, int $sessionId): StreamedResponse
    {
        $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        if (!$session->isActive()) {
            abort(422, 'Session is closed. Start a new session.');
        }

        $userMessage = $request->input('content');

        // Check for explicit rating in user message
        $explicitRating = Signal::detectExplicitRating($userMessage);
        if ($explicitRating !== null) {
            Signal::create([
                'user_id'            => Auth::id(),
                'advisor_session_id' => $session->id,
                'rating'             => $explicitRating,
                'type'               => 'explicit',
                'context'            => 'User provided explicit rating',
                'message_snippet'    => substr($userMessage, 0, 200),
            ]);
        }

        // Add user message to thread
        $session->addMessage('user', $userMessage);

        // Build system prompt with all memory context
        $systemPrompt = (new SystemPromptBuilder(Auth::id()))->build();

        // Get full message history for API
        $messages = $session->getApiMessages();

        return response()->stream(function () use ($session, $systemPrompt, $messages) {
            $fullResponse = '';

            try {
                foreach ($this->claude->stream($systemPrompt, $messages) as $chunk) {
                    $fullResponse .= $chunk;

                    // Send SSE event
                    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
                    ob_flush();
                    flush();
                }

                // Add assistant response to thread
                $session->addMessage('assistant', $fullResponse);

                // Signal stream complete
                echo "data: " . json_encode(['done' => true]) . "\n\n";
                ob_flush();
                flush();

            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
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
     * Get the current system prompt for debugging/transparency.
     */
    public function systemPrompt(): JsonResponse
    {
        $prompt = (new SystemPromptBuilder(Auth::id()))->build();

        return response()->json(['system_prompt' => $prompt]);
    }
}
