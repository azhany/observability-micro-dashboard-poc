<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function index()
    {
        $status = [
            'app' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ];

        // Check MariaDB connection
        try {
            DB::connection()->getPdo();
            $status['database'] = 'ok';
        } catch (\Exception $e) {
            $status['database'] = 'error';
            $status['database_error'] = $e->getMessage();
        }

        // Check Redis connection
        try {
            Redis::connection()->ping();
            $status['redis'] = 'ok';
        } catch (\Exception $e) {
            $status['redis'] = 'error';
            $status['redis_error'] = $e->getMessage();
        }

        // Determine overall status
        $allOk = $status['database'] === 'ok' && $status['redis'] === 'ok';
        $httpCode = $allOk ? 200 : 503;

        return response()->json($status, $httpCode);
    }
}
