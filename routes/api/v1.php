<?php

use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['message' => 'pong', 'timestamp' => now()->toIso8601String()])->name('ping');

// Public invitation endpoint — no auth required
Route::get('/advisor/teams/invitations/{token}', [TeamController::class, 'showInvitation'])->name('advisor.teams.invitations.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::prefix('advisor')->name('advisor.')->group(function () {
        Route::get('/sessions', [ChatController::class, 'index'])->name('sessions.index');
        Route::post('/sessions', [ChatController::class, 'store'])->name('sessions.store');
        Route::get('/sessions/{session}', [ChatController::class, 'show'])->name('sessions.show');
        Route::patch('/sessions/{session}', [ChatController::class, 'update'])->name('sessions.update');
        Route::post('/sessions/{session}/message', [ChatController::class, 'message'])->name('sessions.message')->middleware('throttle:20,1');
        Route::get('/sessions/{session}/stream', [ChatController::class, 'sessionStream'])->name('sessions.stream');
        Route::post('/sessions/{session}/rate', [ChatController::class, 'rate'])->name('sessions.rate');
        Route::post('/sessions/{session}/close', [ChatController::class, 'close'])->name('sessions.close');
        Route::post('/sessions/{session}/share', [ChatController::class, 'share'])->name('sessions.share');
        Route::delete('/sessions/{session}/share', [ChatController::class, 'unshare'])->name('sessions.unshare');
        Route::post('/sessions/{session}/join-link', [ChatController::class, 'generateJoinLink'])->name('sessions.join-link.store');
        Route::delete('/sessions/{session}/join-link', [ChatController::class, 'revokeJoinLink'])->name('sessions.join-link.destroy');
        Route::delete('/sessions/{session}/leave', [ChatController::class, 'leaveSession'])->name('sessions.leave');
        Route::delete('/sessions/{session}', [ChatController::class, 'destroy'])->name('sessions.destroy');

        Route::patch('/personality-traits/{traitName}', [ChatController::class, 'updateTrait'])->name('personality-traits.update');
        Route::delete('/learnings/{learningId}', [ChatController::class, 'deleteLearning'])->name('learnings.delete');
        Route::delete('/profile-observations/{profileId}', [ChatController::class, 'deleteProfileObservation'])->name('profile-observations.delete');

        Route::get('/system-prompt', [ChatController::class, 'systemPrompt'])->name('system-prompt');

        Route::get('/teams/current', [TeamController::class, 'current'])->name('teams.current');
        Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
        Route::post('/teams/invite', [TeamController::class, 'invite'])->name('teams.invite');
        Route::post('/teams/invitations/{token}/accept', [TeamController::class, 'acceptInvitation'])->name('teams.invitations.accept');
        Route::delete('/teams/members/{userId}', [TeamController::class, 'removeMember'])->name('teams.members.destroy');
        Route::delete('/teams', [TeamController::class, 'destroy'])->name('teams.destroy');

        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('/agents/{agentId}', [AgentController::class, 'show'])->name('agents.show');
        Route::patch('/agents/{agentId}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('/agents/{agentId}', [AgentController::class, 'destroy'])->name('agents.destroy');
    });
});
