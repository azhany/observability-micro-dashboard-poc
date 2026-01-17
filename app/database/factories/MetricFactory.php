<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Metric>
 */
class MetricFactory extends Factory
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
            'agent_id' => 'agent-'.fake()->unique()->numberBetween(1, 1000),
            'metric_name' => fake()->randomElement(['cpu.usage', 'memory.usage', 'disk.usage']),
            'value' => fake()->randomFloat(2, 0, 100),
            'timestamp' => now(),
            'dedupe_id' => Str::uuid()->toString(),
        ];
    }
}
