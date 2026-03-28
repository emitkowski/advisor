<?php

use App\Http\Controllers\AdvisorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('advisor.index');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::redirect('/dashboard', '/advisor')->name('dashboard');

    Route::get('/advisor', [AdvisorController::class, 'index'])->name('advisor.index');
    Route::post('/advisor', [AdvisorController::class, 'store'])->name('advisor.store');
    Route::get('/advisor/profile', [AdvisorController::class, 'profile'])->name('advisor.profile');
    Route::get('/advisor/{session}', [AdvisorController::class, 'show'])->name('advisor.show');
});
