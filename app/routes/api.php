<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\MetricController;
use App\Http\Controllers\Api\MetricIngestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth.tenant')->group(function () {
    Route::get('/auth-test', function () {
        $tenant = request()->attributes->get('tenant');

        return response()->json([
            'message' => 'Authentication successful',
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);
    });

    // Metric Ingestion API
    Route::post('/metrics', [MetricIngestionController::class, 'store'])->middleware('throttle:60,1');

    // Metric Query API with resolution support
    Route::get('/metrics', [MetricController::class, 'index']);

    // Alert Query API
    Route::get('/alerts', [AlertController::class, 'index']);
});
