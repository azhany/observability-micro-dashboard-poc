<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricController extends Controller
{
    /**
     * Query metrics with optional resolution parameter
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'resolution' => 'nullable|in:raw,1m,5m',
            'metric_name' => 'nullable|string|max:64',
            'agent_id' => 'nullable|string|max:64',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
        ]);

        // Get authenticated tenant from middleware
        $tenant = $request->attributes->get('tenant');

        // Determine which table to query based on resolution
        $resolution = $validated['resolution'] ?? 'raw';
        $table = $this->getTableForResolution($resolution);
        $valueColumn = $resolution === 'raw' ? 'value' : 'avg_value';
        $timeColumn = $resolution === 'raw' ? 'timestamp' : 'window_start';

        // Build the query
        $query = DB::table($table)
            ->select(
                'metric_name',
                'agent_id',
                DB::raw("CAST({$valueColumn} AS DECIMAL(16,4)) as value"),
                "{$timeColumn} as timestamp"
            )
            ->where('tenant_id', $tenant->id);

        // Filter by metric name if provided
        if (! empty($validated['metric_name'])) {
            $query->where('metric_name', $validated['metric_name']);
        }

        // Filter by agent_id if provided
        if (! empty($validated['agent_id'])) {
            $query->where('agent_id', $validated['agent_id']);
        }

        // Filter by time range if provided
        if (! empty($validated['start_time'])) {
            $query->where($timeColumn, '>=', Carbon::parse($validated['start_time']));
        }

        if (! empty($validated['end_time'])) {
            $query->where($timeColumn, '<=', Carbon::parse($validated['end_time']));
        }

        // Order by time descending and limit results
        $metrics = $query->orderBy($timeColumn, 'desc')
            ->limit(1000)
            ->get();

        return response()->json([
            'resolution' => $resolution,
            'count' => $metrics->count(),
            'data' => $metrics,
        ]);
    }

    /**
     * Get the table name for the given resolution
     */
    private function getTableForResolution(string $resolution): string
    {
        return match ($resolution) {
            '1m' => 'metrics_1m',
            '5m' => 'metrics_5m',
            default => 'metrics_raw',
        };
    }
}
