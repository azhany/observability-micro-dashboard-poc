<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HealthCheckTest extends TestCase
{
    /**
     * Test health check endpoint returns 200 and all services OK.
     */
    public function test_health_check_returns_ok_status(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'app',
            'timestamp',
            'database',
            'redis',
        ]);

        $json = $response->json();
        $this->assertEquals('ok', $json['app']);
        $this->assertEquals('ok', $json['database']);
        $this->assertEquals('ok', $json['redis']);
    }
}
