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
    /**
     * Agents visible to the current user: personal agents + team agents.
     */
    private function visibleAgentsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $teamId = Auth::user()->currentOrOwnedTeam()?->id;

        return Agent::where(function ($q) use ($teamId) {
            $q->where('user_id', Auth::id())->whereNull('team_id');
            if ($teamId) {
                $q->orWhere('team_id', $teamId);
            }
        });
    }

    public function index(Request $request): Response
    {
        Agent::seedDefaults(Auth::id());

        $search  = $request->string('search')->trim()->value();
        $agentId = $request->integer('agent_id') ?: null;
        $status  = $request->string('status')->value();

        $sessions = AdvisorSession::where('user_id', Auth::id())
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($agentId, fn ($q) => $q->where('agent_id', $agentId))
            ->when($status === 'active', fn ($q) => $q->whereNull('ended_at'))
            ->when($status === 'closed', fn ($q) => $q->whereNotNull('ended_at'))
            ->orderBy('created_at', 'desc')
            ->select(['id', 'agent_id', 'title', 'message_count', 'input_tokens', 'output_tokens', 'avg_rating', 'started_at', 'ended_at', 'created_at'])
            ->with('agent:id,name,color')
            ->paginate(20)
            ->withQueryString();

        $agents = $this->visibleAgentsQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'is_preset', 'color', 'sort_order', 'team_id']);

        return Inertia::render('Advisor/Index', [
            'sessions' => $sessions,
            'agents'   => $agents,
            'filters'  => [
                'search'   => $search,
                'agent_id' => $agentId,
                'status'   => $status,
            ],
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
            $agent   = $this->visibleAgentsQuery()->findOrFail($request->input('agent_id'));
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
            'model'   => config('advisor.model'),
            'pricing' => config('advisor.pricing'),
        ]);
    }

    public function profile(): Response
    {
        $userId = Auth::id();
        $teamId = Auth::user()->currentOrOwnedTeam()?->id;

        $projects = Project::where(function ($q) use ($userId, $teamId) {
                $q->where('user_id', $userId);
                if ($teamId) {
                    $q->orWhere('team_id', $teamId);
                }
            })
            ->orderBy('status')
            ->orderBy('name')
            ->get(['name', 'description', 'status', 'notes', 'team_id', 'first_seen_at', 'last_seen_at'])
            ->unique('name');

        return Inertia::render('Advisor/Profile', [
            'profileObservations' => Profile::where('user_id', $userId)
                ->orderByDesc('confidence')
                ->get(['id', 'key', 'value', 'confidence', 'observation_count']),
            'learnings' => Learning::where('user_id', $userId)
                ->orderBy('category')
                ->orderByDesc('confidence')
                ->get(['id', 'category', 'content', 'confidence', 'reinforcement_count', 'last_seen_at']),
            'projects' => $projects->values(),
        ]);
    }

    public function agents(): Response
    {
        $agents = $this->visibleAgentsQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'user_id', 'team_id', 'name', 'description', 'is_preset', 'color', 'sort_order']);

        return Inertia::render('Advisor/Agents/Index', [
            'agents' => $agents,
        ]);
    }

    public function agentCreate(): Response
    {
        return Inertia::render('Advisor/Agents/Form', [
            'agent'       => null,
            'userTeamId'  => Auth::user()->currentOrOwnedTeam()?->id,
        ]);
    }

    public function agentShow(int $agentId): Response
    {
        $agent = $this->visibleAgentsQuery()->findOrFail($agentId);

        $sessionStats = AdvisorSession::where('user_id', Auth::id())
            ->where('agent_id', $agentId)
            ->selectRaw('COUNT(*) as total, SUM(message_count) as total_messages, AVG(avg_rating) as avg_rating')
            ->first();

        return Inertia::render('Advisor/Agents/Show', [
            'agent' => $agent,
            'stats' => [
                'total_sessions'  => (int) $sessionStats->total,
                'total_messages'  => (int) $sessionStats->total_messages,
                'avg_rating'      => $sessionStats->avg_rating ? round($sessionStats->avg_rating, 1) : null,
            ],
        ]);
    }

    public function agentEdit(int $agentId): Response
    {
        // Only the creator can edit
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);

        return Inertia::render('Advisor/Agents/Form', [
            'agent'      => $agent,
            'userTeamId' => Auth::user()->currentOrOwnedTeam()?->id,
        ]);
    }

    public function team(): Response
    {
        $user = Auth::user();
        $team = $user->currentOrOwnedTeam();

        if ($team) {
            $team->load(['members:id,name,email', 'invitations' => fn ($q) => $q->whereNull('accepted_at')->where('expires_at', '>', now())]);
        }

        return Inertia::render('Team/Index', [
            'team' => $team ? [
                'id'          => $team->id,
                'name'        => $team->name,
                'owner_id'    => $team->owner_id,
                'members'     => $team->members->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email])->values(),
                'invitations' => $team->invitations->map(fn ($i) => ['id' => $i->id, 'email' => $i->email, 'expires_at' => $i->expires_at])->values(),
            ] : null,
        ]);
    }

    public function sharedSession(string $token): Response
    {
        $session = AdvisorSession::where('share_token', $token)
            ->with('agent:id,name,color')
            ->firstOrFail();

        $title       = $session->title ?? 'Shared Session';
        $description = $session->summary
            ?? ($session->thread ? collect($session->thread)->first()['content'] ?? null : null);
        $description = $description ? mb_strimwidth($description, 0, 200, '…') : null;

        return Inertia::render('Advisor/Shared', [
            'session' => [
                'title'         => $session->title,
                'summary'       => $session->summary,
                'thread'        => $session->thread ?? [],
                'message_count' => $session->message_count,
                'created_at'    => $session->created_at,
                'ended_at'      => $session->ended_at,
                'agent'         => $session->agent ? [
                    'name'  => $session->agent->name,
                    'color' => $session->agent->color,
                ] : null,
            ],
            'meta' => [
                'title'       => $title,
                'description' => $description,
                'url'         => url()->current(),
                'site_name'   => config('app.name'),
            ],
        ]);
    }
}
