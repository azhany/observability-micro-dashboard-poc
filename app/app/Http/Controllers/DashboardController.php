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
                $lastSeen = \Illuminate\Support\Carbon::parse($metric->last_seen);

                return [
                    'id' => $metric->agent_id,
                    'status' => $lastSeen->diffInSeconds(now()) <= 60 ? 'Online' : 'Offline',
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
        // For PoC: Using first tenant as default
        // In production, this would be: $currentTenant = $request->user()->tenant;
        $currentTenant = Tenant::first();

        /**
         * SEC-001: Multi-tenant Isolation
         * Verify that the requested tenant ID matches the authenticated user's tenant.
         */
        if (! $currentTenant || $currentTenant->id !== $tenant) {
            abort(403, 'Unauthorized access to tenant data.');
        }

        $tenantModel = Tenant::findOrFail($tenant);

        // Get agent_id from query parameter if provided
        $agentId = $request->query('agent_id');

        return Inertia::render('Dashboard/TenantDetail', [
            'tenant' => $tenantModel,
            'agent_id' => $agentId,
        ]);
    }
}
