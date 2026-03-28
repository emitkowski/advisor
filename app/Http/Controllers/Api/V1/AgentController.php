<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = Agent::where('user_id', Auth::id())
            ->orderBy('is_preset', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($agents);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                   => 'required|string|max:100',
            'description'            => 'required|string|max:500',
            'system_prompt_preamble' => 'required|string|max:5000',
            'personality'            => 'required|array|min:1',
            'personality.*.trait'    => 'required|string|max:50',
            'personality.*.value'    => 'required|integer|min:0|max:100',
            'personality.*.description' => 'required|string|max:200',
        ]);

        $agent = Agent::create([
            'user_id'               => Auth::id(),
            'name'                  => $request->input('name'),
            'description'           => $request->input('description'),
            'system_prompt_preamble' => $request->input('system_prompt_preamble'),
            'personality'           => $request->input('personality'),
            'is_preset'             => false,
        ]);

        return response()->json($agent, 201);
    }

    public function show(int $agentId): JsonResponse
    {
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);

        return response()->json($agent);
    }

    public function update(Request $request, int $agentId): JsonResponse
    {
        $request->validate([
            'name'                   => 'sometimes|string|max:100',
            'description'            => 'sometimes|string|max:500',
            'system_prompt_preamble' => 'sometimes|string|max:5000',
            'personality'            => 'sometimes|array|min:1',
            'personality.*.trait'    => 'required_with:personality|string|max:50',
            'personality.*.value'    => 'required_with:personality|integer|min:0|max:100',
            'personality.*.description' => 'required_with:personality|string|max:200',
        ]);

        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);
        $agent->update($request->only(['name', 'description', 'system_prompt_preamble', 'personality']));

        return response()->json($agent);
    }

    public function destroy(int $agentId): JsonResponse
    {
        $agent = Agent::where('user_id', Auth::id())->findOrFail($agentId);
        $agent->delete();

        return response()->json(['message' => 'Agent deleted.']);
    }
}
