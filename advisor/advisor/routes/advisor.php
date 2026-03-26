<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Advisor API Routes
|--------------------------------------------------------------------------
| All routes require authentication via Laravel Sanctum.
| Add these to your routes/api.php inside an auth:sanctum middleware group.
|
| Example:
|   Route::middleware('auth:sanctum')->group(function () {
|       require __DIR__ . '/advisor.php';
|   });
*/

// Sessions
Route::prefix('advisor')->group(function () {

    // List all sessions (paginated)
    Route::get('/sessions', [ChatController::class, 'index']);

    // Create a new session
    Route::post('/sessions', [ChatController::class, 'store']);

    // Get a specific session with thread
    Route::get('/sessions/{session}', [ChatController::class, 'show']);

    // Send a message (returns SSE stream)
    Route::post('/sessions/{session}/message', [ChatController::class, 'message']);

    // Close a session and trigger learning
    Route::post('/sessions/{session}/close', [ChatController::class, 'close']);

    // Debug: view current system prompt
    Route::get('/system-prompt', [ChatController::class, 'systemPrompt']);

});
