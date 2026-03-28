<?php

use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\ChatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['message' => 'pong', 'timestamp' => now()->toIso8601String()])->name('ping');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::prefix('advisor')->name('advisor.')->group(function () {
        Route::get('/sessions', [ChatController::class, 'index'])->name('sessions.index');
        Route::post('/sessions', [ChatController::class, 'store'])->name('sessions.store');
        Route::get('/sessions/{session}', [ChatController::class, 'show'])->name('sessions.show');
        Route::patch('/sessions/{session}', [ChatController::class, 'update'])->name('sessions.update');
        Route::post('/sessions/{session}/message', [ChatController::class, 'message'])->name('sessions.message')->middleware('throttle:20,1');
        Route::post('/sessions/{session}/rate', [ChatController::class, 'rate'])->name('sessions.rate');
        Route::post('/sessions/{session}/close', [ChatController::class, 'close'])->name('sessions.close');

        Route::patch('/personality-traits/{traitName}', [ChatController::class, 'updateTrait'])->name('personality-traits.update');
        Route::delete('/learnings/{learningId}', [ChatController::class, 'deleteLearning'])->name('learnings.delete');
        Route::delete('/profile-observations/{profileId}', [ChatController::class, 'deleteProfileObservation'])->name('profile-observations.delete');

        Route::get('/system-prompt', [ChatController::class, 'systemPrompt'])->name('system-prompt');

        Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
        Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
        Route::get('/agents/{agentId}', [AgentController::class, 'show'])->name('agents.show');
        Route::patch('/agents/{agentId}', [AgentController::class, 'update'])->name('agents.update');
        Route::delete('/agents/{agentId}', [AgentController::class, 'destroy'])->name('agents.destroy');
    });
});
