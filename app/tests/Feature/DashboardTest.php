<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that /dashboard renders Dashboard/Overview component.
     */
    public function test_dashboard_renders_overview_component(): void
    {
        // Create a tenant for testing
        $tenant = Tenant::factory()->create();

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
        );
    }

    /**
     * Test that /dashboard/tenants/{id} renders Dashboard/TenantDetail component.
     */
    public function test_tenant_detail_renders_tenant_detail_component(): void
    {
        $tenant = Tenant::factory()->create();

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/dashboard/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/TenantDetail')
                ->has('tenant')
                ->where('tenant.id', $tenant->id)
        );
    }

    /**
     * Test that agent list data is passed to the overview view.
     */
    public function test_agent_list_data_is_passed_to_overview(): void
    {
        $tenant = Tenant::factory()->create();

        // Create metrics with distinct agent IDs
        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
        ]);

        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-2',
        ]);

        // Create another metric for agent-1 (should still show only 2 distinct agents)
        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
        ]);

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->has('agents', 2)
                ->where('agents.0.status', 'Online')
                ->where('agents.1.status', 'Online')
                ->has('tenant')
                ->where('tenant.id', $tenant->id)
        );

        // Verify both agent IDs are present (order not guaranteed)
        $agents = $response->viewData('page')['props']['agents'];
        $agentIds = array_column($agents, 'id');
        $this->assertContains('agent-1', $agentIds);
        $this->assertContains('agent-2', $agentIds);
    }

    /**
     * Test dashboard with no tenant returns empty agents list.
     */
    public function test_dashboard_with_no_tenant_returns_empty_agents(): void
    {
        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->where('agents', [])
                ->where('tenant', null)
        );
    }

    /**
     * Test tenant detail page accepts agent_id query parameter.
     */
    public function test_tenant_detail_accepts_agent_id_parameter(): void
    {
        $tenant = Tenant::factory()->create();
        $agentId = 'agent-123';

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get("/dashboard/tenants/{$tenant->id}?agent_id={$agentId}");

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/TenantDetail')
                ->has('tenant')
                ->where('tenant.id', $tenant->id)
                ->where('agent_id', $agentId)
        );
    }
}
