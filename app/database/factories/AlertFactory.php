<?php

namespace Database\Factories;

use App\Models\AlertRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Alert>
 */
class AlertFactory extends Factory
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
            'alert_rule_id' => AlertRule::factory(),
            'state' => fake()->randomElement(['OK', 'PENDING', 'FIRING']),
            'started_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'last_checked_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function ok(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'OK',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'PENDING',
        ]);
    }

    public function firing(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'FIRING',
        ]);
    }
}
