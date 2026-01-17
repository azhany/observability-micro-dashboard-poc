<?php

namespace App\Http\Controllers;

use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Http\Request;
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
        // In production, this would come from authenticated user context
        $tenant = Tenant::first();

        if (! $tenant) {
            // No tenant found, return empty state
            return Inertia::render('Dashboard/Overview', [
                'agents' => [],
                'tenant' => null,
            ]);
        }

        // Get distinct agent_ids for this tenant
        $agents = Metric::where('tenant_id', $tenant->id)
            ->select('agent_id')
            ->distinct()
            ->get()
            ->map(function ($metric) {
                return [
                    'id' => $metric->agent_id,
                    'status' => 'Online', // Placeholder status
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
        $tenantModel = Tenant::findOrFail($tenant);

        // Get agent_id from query parameter if provided
        $agentId = $request->query('agent_id');

        return Inertia::render('Dashboard/TenantDetail', [
            'tenant' => $tenantModel,
            'agent_id' => $agentId,
        ]);
    }
}
