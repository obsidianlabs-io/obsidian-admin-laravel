<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic API health check.
     */
    public function test_the_api_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'service' => 'obsidian-admin-laravel',
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'name',
                'service',
                'status',
                'timestamp',
            ]);
    }
}
