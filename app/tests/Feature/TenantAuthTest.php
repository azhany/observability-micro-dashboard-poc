<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_authentication_with_valid_token(): void
    {
        // Create a tenant
        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'settings' => [],
        ]);

        // Generate a token
        $token = Str::random(64);

        // Store the hashed token
        TenantToken::create([
            'tenant_id' => $tenant->id,
            'token' => hash('sha256', $token),
        ]);

        // Make a request with the valid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth-test');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Authentication successful',
                'tenant_id' => $tenant->id,
                'tenant_name' => 'Test Tenant',
            ]);
    }

    public function test_401_response_with_invalid_token(): void
    {
        // Make a request with an invalid token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])->getJson('/api/v1/auth-test');

        // Assert 401 Unauthorized
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    public function test_401_response_with_missing_token(): void
    {
        // Make a request without an Authorization header
        $response = $this->getJson('/api/v1/auth-test');

        // Assert 401 Unauthorized
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    public function test_tenant_isolation(): void
    {
        // Create Tenant A
        $tenantA = Tenant::create(['name' => 'Tenant A']);
        $tokenA = Str::random(64);
        TenantToken::create([
            'tenant_id' => $tenantA->id,
            'token' => hash('sha256', $tokenA),
        ]);

        // Create Tenant B
        $tenantB = Tenant::create(['name' => 'Tenant B']);
        $tokenB = Str::random(64);
        TenantToken::create([
            'tenant_id' => $tenantB->id,
            'token' => hash('sha256', $tokenB),
        ]);

        // Request with Token A should return Tenant A context
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
        ])->getJson('/api/v1/auth-test');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $tenantA->id,
                'tenant_name' => 'Tenant A',
            ]);

        // Request with Token B should return Tenant B context
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenB,
        ])->getJson('/api/v1/auth-test');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $tenantB->id,
                'tenant_name' => 'Tenant B',
            ]);
    }
}
