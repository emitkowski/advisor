<?php

namespace App\Http\Controllers;

use App\Models\AdvisorSession;
use App\Models\PersonalityTrait;
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
}
