<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ApiResultCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_flag_index_returns_paginated_records(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/system/feature-flags?current=1&size=50&keyword=themeConfig', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('data.current', 1)
            ->assertJsonPath('data.size', 50);

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);
        $this->assertArrayHasKey('key', $records[0]);

        $keys = array_values(array_map(
            static fn (array $record): string => (string) ($record['key'] ?? ''),
            $records
        ));
        $this->assertContains('menu.themeConfig', $keys);
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
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
            ->assertJsonPath('data.key', 'menu.permission')
            ->assertJsonPath('data.global_override', false);

        $purgeResponse = $this->deleteJson('/api/system/feature-flags/purge', [
            'key' => 'menu.permission',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $purgeResponse->assertOk()
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value)
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
            ->assertJsonPath('code', ApiResultCode::SUCCESS->value);

        return (string) $response->json('data.token');
    }
}
