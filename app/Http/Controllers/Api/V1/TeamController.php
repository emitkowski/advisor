<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TeamController extends Controller
{
    public function current(): JsonResponse
    {
        $team = Auth::user()->currentOrOwnedTeam();

        if (!$team) {
            return response()->json(null);
        }

        $team->load(['members:id,name,email', 'invitations' => fn ($q) => $q->whereNull('accepted_at')->where('expires_at', '>', now())]);

        return response()->json([
            'id'          => $team->id,
            'name'        => $team->name,
            'owner_id'    => $team->owner_id,
            'members'     => $team->members->map(fn ($m) => [
                'id'    => $m->id,
                'name'  => $m->name,
                'email' => $m->email,
            ]),
            'invitations' => $team->invitations->map(fn ($i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'expires_at' => $i->expires_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string|max:100']);

        if (Auth::user()->ownedTeam) {
            return response()->json(['message' => 'You already own a team.'], 400);
        }

        $team = Team::create([
            'owner_id' => Auth::id(),
            'name'     => $request->input('name'),
        ]);

        $team->members()->attach(Auth::id());
        Auth::user()->update(['current_team_id' => $team->id]);

        return response()->json($team, 201);
    }

    public function invite(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = Auth::user();
        $team = $user->ownedTeam;

        abort_unless($team, 403, 'You must own a team to invite members.');
        abort_if($request->input('email') === $user->email, 422, 'You cannot invite yourself.');

        $alreadyMember = $team->members()->where('users.email', $request->input('email'))->exists();
        abort_if($alreadyMember, 422, 'That user is already a team member.');

        // Revoke any pending invitation for the same email before creating a new one
        $team->invitations()->where('email', $request->input('email'))->whereNull('accepted_at')->delete();

        $invitation = TeamInvitation::generate($team, $request->input('email'), Auth::id());

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        return response()->json(['message' => 'Invitation sent.']);
    }

    public function showInvitation(string $token): JsonResponse
    {
        $invitation = TeamInvitation::with(['team', 'inviter'])->where('token', $token)->firstOrFail();

        if (!$invitation->isPending()) {
            $reason = $invitation->accepted_at ? 'already_accepted' : 'expired';

            return response()->json(['valid' => false, 'reason' => $reason]);
        }

        return response()->json([
            'valid'        => true,
            'team_name'    => $invitation->team->name,
            'inviter_name' => $invitation->inviter?->name,
            'email'        => $invitation->email,
            'expires_at'   => $invitation->expires_at,
        ]);
    }

    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = TeamInvitation::with('team')->where('token', $token)->firstOrFail();

        abort_unless($invitation->isPending(), 410, 'This invitation is no longer valid.');
        abort_unless($request->user()->email === $invitation->email, 403, 'This invitation was sent to a different email address.');
        abort_if($invitation->team->hasMember($request->user()), 422, 'You are already a member of this team.');

        $invitation->team->members()->attach($request->user()->id);
        $invitation->update(['accepted_at' => now()]);
        $request->user()->update(['current_team_id' => $invitation->team->id]);

        return response()->json(['message' => 'You have joined the team.']);
    }

    public function removeMember(int $userId): JsonResponse
    {
        $team = Auth::user()->ownedTeam;

        abort_unless($team, 403, 'You do not own a team.');
        abort_if($userId === Auth::id(), 422, 'You cannot remove yourself from your own team.');

        $team->members()->detach($userId);

        User::where('id', $userId)
            ->where('current_team_id', $team->id)
            ->update(['current_team_id' => null]);

        return response()->json(['message' => 'Member removed.']);
    }

    public function destroy(): JsonResponse
    {
        $team = Auth::user()->ownedTeam;

        abort_unless($team, 403, 'You do not own a team.');

        Agent::where('team_id', $team->id)->update(['team_id' => null]);
        Project::where('team_id', $team->id)->update(['team_id' => null]);
        User::where('current_team_id', $team->id)->update(['current_team_id' => null]);

        $team->delete();

        return response()->json(['message' => 'Team disbanded.']);
    }
}
