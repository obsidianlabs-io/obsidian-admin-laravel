<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureRolloutCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_feature_rollout_can_disable_permission_menu(): void
    {
        $this->seed();

        $this->artisan('feature:rollout menu.permission off --global')
            ->expectsOutputToContain('[global] disabled: menu.permission')
            ->assertSuccessful();

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.routeRules.permission.enabled', false);
    }

    public function test_scoped_rollout_can_disable_and_reset_feature(): void
    {
        $this->seed();
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $tenantId = (int) $mainTenant->id;

        $this->artisan(sprintf('feature:rollout menu.role off --tenant=%d --roles=R_ADMIN', $tenantId))
            ->expectsOutputToContain(sprintf('[scope=tenant:%d|roles:R_ADMIN] disabled: menu.role', $tenantId))
            ->assertSuccessful();

        $token = $this->loginAndGetToken('Admin');
        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.routeRules.role.enabled', false);

        $this->artisan(sprintf('feature:rollout menu.role reset --tenant=%d --roles=R_ADMIN', $tenantId))
            ->expectsOutputToContain(sprintf('[scope=tenant:%d|roles:R_ADMIN] reset: menu.role', $tenantId))
            ->assertSuccessful();

        $responseAfterReset = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $responseAfterReset->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.routeRules.role.enabled', true);
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
