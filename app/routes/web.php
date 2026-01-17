<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/health', [HealthController::class, 'index']);

// Dashboard routes (auth required)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/tenants/{tenant}', [DashboardController::class, 'show'])->name('dashboard.tenant.show');

    // SSE Stream endpoint
    Route::get('/api/v1/stream/{tenant}', [StreamController::class, 'stream'])->name('stream.metrics');
});
