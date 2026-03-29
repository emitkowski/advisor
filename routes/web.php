<?php

use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('advisor.index');
});

Route::get('/shared/{token}', [AdvisorController::class, 'sharedSession'])->name('advisor.shared');
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('team.invitation');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::redirect('/dashboard', '/advisor')->name('dashboard');

    Route::get('/advisor', [AdvisorController::class, 'index'])->name('advisor.index');
    Route::post('/advisor', [AdvisorController::class, 'store'])->name('advisor.store');
    Route::get('/advisor/profile', [AdvisorController::class, 'profile'])->name('advisor.profile');
    Route::get('/advisor/agents', [AdvisorController::class, 'agents'])->name('advisor.agents');
    Route::get('/advisor/agents/create', [AdvisorController::class, 'agentCreate'])->name('advisor.agents.create');
    Route::get('/advisor/agents/{agentId}', [AdvisorController::class, 'agentShow'])->name('advisor.agents.show');
    Route::get('/advisor/agents/{agentId}/edit', [AdvisorController::class, 'agentEdit'])->name('advisor.agents.edit');
    Route::get('/advisor/team', [AdvisorController::class, 'team'])->name('advisor.team');
    Route::get('/advisor/{session}', [AdvisorController::class, 'show'])->name('advisor.show');
});
