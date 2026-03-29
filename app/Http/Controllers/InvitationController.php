<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = TeamInvitation::with(['team', 'inviter'])->where('token', $token)->first();

        if (!$invitation) {
            return Inertia::render('Team/AcceptInvitation', [
                'valid'  => false,
                'reason' => 'not_found',
            ]);
        }

        if (!$invitation->isPending()) {
            return Inertia::render('Team/AcceptInvitation', [
                'valid'  => false,
                'reason' => $invitation->accepted_at ? 'already_accepted' : 'expired',
            ]);
        }

        return Inertia::render('Team/AcceptInvitation', [
            'valid'        => true,
            'token'        => $token,
            'team_name'    => $invitation->team->name,
            'inviter_name' => $invitation->inviter?->name,
            'email'        => $invitation->email,
            'expires_at'   => $invitation->expires_at,
        ]);
    }
}
