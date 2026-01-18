<?php

namespace App\Http\Controllers;

use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard overview with agent list.
     */
    public function index(Request $request): Response
    {
        // For PoC: Using first tenant as default
        // In production, this would come from authenticated user context ($request->user()->tenant)
        $tenant = Tenant::first();

        if (! $tenant) {
            // No tenant found, return empty state
            return Inertia::render('Dashboard/Overview', [
                'agents' => [],
                'tenant' => null,
            ]);
        }

        /**
         * PERF-001: Scalability Risk
         * Performing DISTINCT on metrics_raw will become slow as the table grows.
         * TODO: Implement an Agent registry or cached list to avoid full table scans.
         */
        $agents = Metric::where('tenant_id', $tenant->id)
            ->select('agent_id', DB::raw('MAX(timestamp) as last_seen'))
            ->groupBy('agent_id')
            ->get()
            ->map(function ($metric) {
                $lastSeenTimestamp = strtotime($metric->last_seen);
                $currentTimestamp = time();
                $secondsSinceLastSeen = $currentTimestamp - $lastSeenTimestamp;

                return [
                    'id' => $metric->agent_id,
                    'status' => $secondsSinceLastSeen <= 60 ? 'Online' : 'Offline',
                    'last_seen' => $metric->last_seen,
                ];
            });

        return Inertia::render('Dashboard/Overview', [
            'agents' => $agents,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Display the tenant detail page.
     */
    public function show(Request $request, string $tenant): Response
    {
        /**
         * SEC-001: Multi-tenant Isolation
         * TODO: Ensure authenticated user has access to this specific tenant.
         * Currently relies on finding by ID without ownership verification.
         */
        $tenantModel = Tenant::findOrFail($tenant);

        // Get agent_id from query parameter if provided
        $agentId = $request->query('agent_id');

        return Inertia::render('Dashboard/TenantDetail', [
            'tenant' => $tenantModel,
            'agent_id' => $agentId,
        ]);
    }
}
