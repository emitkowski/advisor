<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    private function teamId(): ?int
    {
        return Auth::user()->currentOrOwnedTeam()?->id;
    }

    private function visibleAgents(): \Illuminate\Database\Eloquent\Builder
    {
        $teamId = $this->teamId();

        return Agent::where(function ($q) use ($teamId) {
            $q->where('user_id', Auth::id())->whereNull('team_id');
            if ($teamId) {
                $q->orWhere('team_id', $teamId);
            }
        });
    }

    public function index(): JsonResponse
    {
        $agents = $this->visibleAgents()
            ->orderBy('is_preset', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($agents);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                   => 'required|string|max:100',
            'description'            => 'required|string|max:500',
            'system_prompt_preamble' => 'required|string|max:5000',
            'algorithm'              => 'nullable|string|max:5000',
            'color'                  => 'nullable|string|max:20',
            'team_id'                => 'nullable|integer',
            'personality'            => 'required|array|min:1',
            'personality.*.trait'    => 'required|string|max:50',
            'personality.*.value'    => 'required|integer|min:0|max:100',
            'personality.*.description' => 'required|string|max:200',
        ]);

        if (!empty($validated['team_id'])) {
            abort_unless($validated['team_id'] === $this->teamId(), 403, 'Invalid team.');
        }

        $agent = Agent::create([
            'user_id'               => Auth::id(),
            'team_id'               => $validated['team_id'] ?? null,
            'name'                  => $validated['name'],
            'description'           => $validated['description'],
            'system_prompt_preamble' => $validated['system_prompt_preamble'],
            'algorithm'             => $validated['algorithm'] ?? null,
            'color'                 => $validated['color'] ?? null,
            'personality'           => $validated['personality'],
            'is_preset'             => false,
        ]);

        return response()->json($agent, 201);
    }

    public function show(int $agentId): JsonResponse
    {
        $agent = $this->visibleAgents()->findOrFail($agentId);

        return response()->json($agent);
    }

    public function update(Request $request, int $agentId): JsonResponse
    {
        $request->validate([
            'name'                   => 'sometimes|string|max:100',
            'description'            => 'sometimes|string|max:500',
            'system_prompt_preamble' => 'sometimes|string|max:5000',
            'algorithm'              => 'sometimes|nullable|string|max:5000',
            'color'                  => 'sometimes|nullable|string|max:20',
            'team_id'                => 'sometimes|nullable|integer',
            'personality'            => 'sometimes|array|min:1',
            'personality.*.trait'    => 'required_with:personality|string|max:50',
            'personality.*.value'    => 'required_with:personality|integer|min:0|max:100',
            'personality.*.description' => 'required_with:personality|string|max:200',
        ]);

        // Only the creator can mutate an agent
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);

        if ($request->has('team_id') && $request->input('team_id') !== null) {
            abort_unless($request->input('team_id') === $this->teamId(), 403, 'Invalid team.');
        }

        $agent->update($request->only(['name', 'description', 'system_prompt_preamble', 'algorithm', 'color', 'team_id', 'personality']));

        return response()->json($agent);
    }

    public function destroy(int $agentId): JsonResponse
    {
        // Only the creator can delete an agent
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);
        $agent->delete();

        return response()->json(['message' => 'Agent deleted.']);
    }
}
