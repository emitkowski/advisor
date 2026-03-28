<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AdvisorSession;
use App\Models\Learning;
use App\Models\PersonalityTrait;
use App\Models\Profile;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdvisorController extends Controller
{
    public function index(): Response
    {
        Agent::seedDefaults(Auth::id());

        $sessions = AdvisorSession::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->select(['id', 'agent_id', 'title', 'message_count', 'input_tokens', 'output_tokens', 'avg_rating', 'started_at', 'ended_at', 'created_at'])
            ->with('agent:id,name,color')
            ->paginate(20);

        $agents = Agent::where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_preset', 'color', 'sort_order']);

        return Inertia::render('Advisor/Index', [
            'sessions' => $sessions,
            'agents'   => $agents,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()->route('advisor.show', $session->id);
    }

    public function show(int $sessionId): Response
    {
        $session = AdvisorSession::where('user_id', Auth::id())
            ->with('agent')
            ->findOrFail($sessionId);

        return Inertia::render('Advisor/Chat', [
            'session' => $session,
        ]);
    }

    public function profile(): Response
    {
        $userId = Auth::id();

        return Inertia::render('Advisor/Profile', [
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

    public function agents(): Response
    {
        $agents = Agent::where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_preset', 'color', 'sort_order']);

        return Inertia::render('Advisor/Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function agentCreate(): Response
    {
        return Inertia::render('Advisor/Agents/Form', [
            'agent' => null,
        ]);
    }

    public function agentEdit(int $agentId): Response
    {
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);

        return Inertia::render('Advisor/Agents/Form', [
            'agent' => $agent,
        ]);
    }
}
