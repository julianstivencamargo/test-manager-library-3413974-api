<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_ok_on_api_prefix(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_health_check_endpoint_returns_ok_on_root_health(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }
}
