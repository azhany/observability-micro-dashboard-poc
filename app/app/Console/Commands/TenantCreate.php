<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant and generate an API token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        // Create tenant
        $tenant = Tenant::create([
            'name' => $name,
            'settings' => [],
        ]);

        // Generate a secure 64-character random token
        $token = Str::random(64);

        // Store the SHA-256 hash of the token in the database
        TenantToken::create([
            'tenant_id' => $tenant->id,
            'token' => hash('sha256', $token),
        ]);

        $this->info("Tenant '{$name}' created successfully!");
        $this->info("Tenant ID: {$tenant->id}");
        $this->newLine();
        $this->warn('API Token (store this securely, it will not be shown again):');
        $this->line($token);

        return Command::SUCCESS;
    }
}
