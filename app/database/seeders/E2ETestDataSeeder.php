<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantToken;
use Illuminate\Database\Seeder;

/**
 * E2E Test Data Seeder
 *
 * Creates test data for Playwright E2E smoke tests.
 * This seeder should be run before executing E2E tests.
 *
 * Usage: php artisan db:seed --class=E2ETestDataSeeder
 */
class E2ETestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the test tenant
        $tenant = Tenant::firstOrCreate(
            ['id' => 'tenant-demo'],
            [
                'name' => 'Demo Tenant (E2E Test)',
                'settings' => [],
            ]
        );

        // Create the test token
        // Token: test-token-12345678901234567890123456789012
        // This token is used in the Playwright smoke tests
        $plainTextToken = 'test-token-12345678901234567890123456789012';
        $hashedToken = hash('sha256', $plainTextToken);

        TenantToken::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'token' => $hashedToken,
            ],
            [
                'last_used_at' => null,
            ]
        );

        // Create a test user for E2E authentication
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'E2E Test User',
                'password' => bcrypt('password'), // Simple password for testing
            ]
        );

        // Associate user with the test tenant
        if (! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            $user->tenants()->attach($tenant->id);
        }

        $this->command->info('✅ E2E test data created successfully!');
        $this->command->info("   Tenant ID: {$tenant->id}");
        $this->command->info("   Tenant Name: {$tenant->name}");
        $this->command->info('   Test Token: '.$plainTextToken);
        $this->command->info('   Test User: test@example.com / password');
        $this->command->info('');
        $this->command->info('You can now run E2E tests with: npm run test:e2e');
    }
}
