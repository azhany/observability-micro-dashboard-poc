<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/health', [HealthController::class, 'index']);
