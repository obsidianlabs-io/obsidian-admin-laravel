<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_index_returns_paginated_records(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/system/feature-flags?current=1&size=5', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.current', 1)
            ->assertJsonPath('data.size', 5);

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
        $this->assertArrayHasKey('key', $records[0]);
    }

    public function test_feature_flag_can_toggle_and_purge_global_override(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $toggleResponse = $this->putJson('/api/system/feature-flags/toggle', [
            'key' => 'menu.permission',
            'enabled' => false,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $toggleResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.key', 'menu.permission')
            ->assertJsonPath('data.global_override', false);

        $purgeResponse = $this->deleteJson('/api/system/feature-flags/purge', [
            'key' => 'menu.permission',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $purgeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.key', 'menu.permission')
            ->assertJsonPath('data.global_override', null);
    }

    private function loginAndGetToken(string $userName): string
    {
        $response = $this->postJson('/api/auth/login', [
            'userName' => $userName,
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        return (string) $response->json('data.token');
    }
}
