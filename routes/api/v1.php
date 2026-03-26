<?php

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
        Route::post('/sessions/{session}/message', [ChatController::class, 'message'])->name('sessions.message')->middleware('throttle:20,1');
        Route::post('/sessions/{session}/close', [ChatController::class, 'close'])->name('sessions.close');
        Route::get('/system-prompt', [ChatController::class, 'systemPrompt'])->name('system-prompt');
    });
});
