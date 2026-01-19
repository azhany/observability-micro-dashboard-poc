<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    /**
     * Query alerts with optional time range and metric filter
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'metric_name' => 'nullable|string|max:64',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'state' => 'nullable|in:OK,PENDING,FIRING',
        ]);

        // Get authenticated tenant from middleware
        $tenant = $request->attributes->get('tenant');

        // Build the query
        $query = Alert::query()
            ->with('alertRule')
            ->where('tenant_id', $tenant->id);

        // Filter by metric name if provided (via alert rule)
        if (!empty($validated['metric_name'])) {
            $query->whereHas('alertRule', function ($q) use ($validated) {
                $q->where('metric_name', $validated['metric_name']);
            });
        }

        // Filter by state if provided
        if (!empty($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        // Filter by time range - use started_at for when alert state changed
        if (!empty($validated['start_time'])) {
            $query->where('started_at', '>=', Carbon::parse($validated['start_time']));
        }

        if (!empty($validated['end_time'])) {
            $query->where('started_at', '<=', Carbon::parse($validated['end_time']));
        }

        // Calculate appropriate limit based on time range
        // If no time range specified, use conservative limit of 100
        // If time range specified, allow more results (up to 1000) to ensure coverage
        $limit = 100;
        if (!empty($validated['start_time']) || !empty($validated['end_time'])) {
            // For historical queries with time range, allow up to 1000 results
            // This ensures 7-day views aren't truncated on noisy systems
            $limit = 1000;
        }

        // Order by most recent first
        $alerts = $query->orderBy('started_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($alert) {
                return [
                    'id' => $alert->id,
                    'metric_name' => $alert->alertRule->metric_name,
                    'state' => $alert->state,
                    'started_at' => $alert->started_at?->toISOString(),
                    'last_checked_at' => $alert->last_checked_at?->toISOString(),
                    'threshold' => $alert->alertRule->threshold,
                    'operator' => $alert->alertRule->operator,
                ];
            });

        return response()->json([
            'count' => $alerts->count(),
            'data' => $alerts,
            'limit' => $limit,
            'has_more' => $alerts->count() === $limit,
        ]);
    }
}
