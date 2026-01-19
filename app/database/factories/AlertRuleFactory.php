<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertRule>
 */
class AlertRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'metric_name' => fake()->randomElement(['cpu_usage', 'memory_usage', 'disk_usage', 'network_io']),
            'operator' => fake()->randomElement(['>', '<', '>=', '<=', '=']),
            'threshold' => fake()->randomFloat(2, 0, 100),
            'duration' => fake()->numberBetween(30, 300),
        ];
    }
}
