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
                ->has('agents.0.last_seen')
                ->has('agents.1.last_seen')
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
     * Test that agent status is "Online" when last_seen is within 60 seconds.
     */
    public function test_agent_status_is_online_when_last_seen_is_recent(): void
    {
        $tenant = Tenant::factory()->create();

        // Create a metric with a recent timestamp (within 60 seconds)
        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
            'timestamp' => now(),
        ]);

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->has('agents', 1)
                ->where('agents.0.id', 'agent-1')
                ->where('agents.0.status', 'Online')
                ->has('agents.0.last_seen')
        );
    }

    /**
     * Test that agent status is "Offline" when last_seen is older than 60 seconds.
     */
    public function test_agent_status_is_offline_when_last_seen_is_old(): void
    {
        $tenant = Tenant::factory()->create();

        // Create a metric with an old timestamp (older than 60 seconds)
        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
            'timestamp' => now()->subSeconds(65),
        ]);

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->has('agents', 1)
                ->where('agents.0.id', 'agent-1')
                ->where('agents.0.status', 'Offline')
                ->has('agents.0.last_seen')
        );
    }

    /**
     * Test that last_seen reflects the most recent metric timestamp for each agent.
     */
    public function test_last_seen_reflects_most_recent_metric_timestamp(): void
    {
        $tenant = Tenant::factory()->create();

        $olderTimestamp = now()->subSeconds(120);
        $newerTimestamp = now()->subSeconds(30);

        // Create multiple metrics for the same agent with different timestamps
        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
            'timestamp' => $olderTimestamp,
        ]);

        Metric::factory()->create([
            'tenant_id' => $tenant->id,
            'agent_id' => 'agent-1',
            'timestamp' => $newerTimestamp,
        ]);

        // Authenticate as a user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->has('agents', 1)
                ->where('agents.0.id', 'agent-1')
                ->where('agents.0.status', 'Online')
        );

        // Verify last_seen is the newer timestamp
        $agents = $response->viewData('page')['props']['agents'];
        $lastSeen = $agents[0]['last_seen'];
        $this->assertEquals($newerTimestamp->format('Y-m-d H:i:s'), $lastSeen);
    }

    /**
     * Test that dashboard only shows agents for the current tenant.
     */
    public function test_dashboard_only_shows_agents_for_current_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create metrics for tenant 1
        Metric::factory()->create([
            'tenant_id' => $tenant1->id,
            'agent_id' => 'tenant-1-agent',
        ]);

        // Create metrics for tenant 2
        Metric::factory()->create([
            'tenant_id' => $tenant2->id,
            'agent_id' => 'tenant-2-agent',
        ]);

        // Authenticate as a user (associated with tenant 1 in this PoC context)
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // The current controller logic picks Tenant::first(), so we ensure tenant1 is first
        // by creating it first or manipulating the query if needed, but for PoC it's fine.
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn (Assert $page) => $page->component('Dashboard/Overview')
                ->has('agents', 1)
                ->where('agents.0.id', 'tenant-1-agent')
        );
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

    /**
     * Test that a user cannot access a different tenant's detail page.
     */
    public function test_tenant_detail_prevents_access_to_other_tenant(): void
    {
        $tenant1 = Tenant::factory()->create(); // This will be the "first" tenant (session tenant in PoC)
        $tenant2 = Tenant::factory()->create();

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Attempt to access tenant 2 while "logged in" as tenant 1
        $response = $this->get("/dashboard/tenants/{$tenant2->id}");

        $response->assertStatus(403);
    }
}
