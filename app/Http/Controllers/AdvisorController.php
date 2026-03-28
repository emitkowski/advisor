<?php

namespace App\Http\Controllers;

use App\Models\AdvisorSession;
use App\Models\Learning;
use App\Models\PersonalityTrait;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorController extends Controller
{
    public function index(): Response
    {
        $sessions = AdvisorSession::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->select(['id', 'title', 'message_count', 'input_tokens', 'output_tokens', 'avg_rating', 'started_at', 'ended_at', 'created_at'])
            ->paginate(20);

        return Inertia::render('Advisor/Index', [
            'sessions' => $sessions,
        ]);
    }

    public function store(): RedirectResponse
    {
        PersonalityTrait::seedDefaults(Auth::id());

        $session = AdvisorSession::create([
            'user_id'    => Auth::id(),
            'started_at' => now(),
        ]);

        return redirect()->route('advisor.show', $session->id);
    }

    public function show(int $sessionId): Response
    {
        $session = AdvisorSession::where('user_id', Auth::id())
            ->findOrFail($sessionId);

        return Inertia::render('Advisor/Chat', [
            'session' => $session,
        ]);
    }

    public function profile(): Response
    {
        $userId = Auth::id();

        return Inertia::render('Advisor/Profile', [
            'personalityTraits' => PersonalityTrait::where('user_id', $userId)
                ->orderBy('trait')
                ->get(['trait', 'value', 'description', 'is_system']),
            'profileObservations' => Profile::where('user_id', $userId)
                ->orderByDesc('confidence')
                ->get(['id', 'key', 'value', 'confidence', 'observation_count']),
            'learnings' => Learning::where('user_id', $userId)
                ->orderBy('category')
                ->orderByDesc('confidence')
                ->get(['id', 'category', 'content', 'confidence', 'reinforcement_count', 'last_seen_at']),
            'projects' => Project::where('user_id', $userId)
                ->orderBy('status')
                ->orderBy('name')
                ->get(['name', 'description', 'status', 'notes', 'first_seen_at', 'last_seen_at']),
        ]);
    }
}
