<?php

use App\Http\Controllers\AdvisorController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/advisor', [AdvisorController::class, 'index'])->name('advisor.index');
    Route::post('/advisor', [AdvisorController::class, 'store'])->name('advisor.store');
    Route::get('/advisor/{session}', [AdvisorController::class, 'show'])->name('advisor.show');
});
