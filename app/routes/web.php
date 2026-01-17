<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/health', [HealthController::class, 'index']);
