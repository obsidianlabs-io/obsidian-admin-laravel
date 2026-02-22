<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Tenant\Models\Tenant;
use Database\Seeders\ThemeProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ThemeConfigApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_update_platform_theme_config(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $showResponse = $this->getJson('/api/theme/config', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $showResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.scopeType', 'platform');

        $updateResponse = $this->putJson('/api/theme/config', [
            'themeColor' => '#1f7ae0',
            'themeRadius' => 8,
            'headerHeight' => 60,
            'siderCollapsedWidth' => 72,
            'layoutMode' => 'top-hybrid-header-first',
            'scrollMode' => 'wrapper',
            'darkSider' => true,
            'themeSchemaVisible' => false,
            'headerFullscreenVisible' => false,
            'multilingualVisible' => false,
            'globalSearchVisible' => false,
            'themeConfigVisible' => false,
            'tabFullscreenVisible' => false,
            'footerVisible' => false,
            'footerHeight' => 40,
            'pageAnimate' => false,
            'pageAnimateMode' => 'fade-scale',
            'fixedHeaderAndTab' => false,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.scopeType', 'platform')
            ->assertJsonPath('data.config.themeColor', '#1f7ae0')
            ->assertJsonPath('data.config.themeRadius', 8)
            ->assertJsonPath('data.config.headerHeight', 60)
            ->assertJsonPath('data.config.siderCollapsedWidth', 72)
            ->assertJsonPath('data.config.layoutMode', 'top-hybrid-header-first')
            ->assertJsonPath('data.config.scrollMode', 'wrapper')
            ->assertJsonPath('data.config.darkSider', true)
            ->assertJsonPath('data.config.themeSchemaVisible', false)
            ->assertJsonPath('data.config.headerFullscreenVisible', false)
            ->assertJsonPath('data.config.multilingualVisible', false)
            ->assertJsonPath('data.config.globalSearchVisible', false)
            ->assertJsonPath('data.config.themeConfigVisible', false)
            ->assertJsonPath('data.config.tabFullscreenVisible', false)
            ->assertJsonPath('data.config.footerVisible', false)
            ->assertJsonPath('data.config.footerHeight', 40)
            ->assertJsonPath('data.config.pageAnimate', false)
            ->assertJsonPath('data.config.pageAnimateMode', 'fade-scale')
            ->assertJsonPath('data.config.fixedHeaderAndTab', false);

        $userInfoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $userInfoResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.themeConfig.themeColor', '#1f7ae0')
            ->assertJsonPath('data.themeConfig.themeRadius', 8)
            ->assertJsonPath('data.themeConfig.headerHeight', 60)
            ->assertJsonPath('data.themeConfig.siderCollapsedWidth', 72)
            ->assertJsonPath('data.themeConfig.layoutMode', 'top-hybrid-header-first')
            ->assertJsonPath('data.themeConfig.scrollMode', 'wrapper')
            ->assertJsonPath('data.themeConfig.darkSider', true)
            ->assertJsonPath('data.themeConfig.themeSchemaVisible', false)
            ->assertJsonPath('data.themeConfig.headerFullscreenVisible', false)
            ->assertJsonPath('data.themeConfig.multilingualVisible', false)
            ->assertJsonPath('data.themeConfig.globalSearchVisible', false)
            ->assertJsonPath('data.themeConfig.themeConfigVisible', false)
            ->assertJsonPath('data.themeConfig.tabFullscreenVisible', false)
            ->assertJsonPath('data.themeConfig.footerVisible', false)
            ->assertJsonPath('data.themeConfig.footerHeight', 40)
            ->assertJsonPath('data.themeConfig.pageAnimate', false)
            ->assertJsonPath('data.themeConfig.pageAnimateMode', 'fade-scale')
            ->assertJsonPath('data.themeConfig.fixedHeaderAndTab', false);
    }

    public function test_admin_cannot_access_theme_config_console(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $showResponse = $this->getJson('/api/theme/config', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse = $this->putJson('/api/theme/config', [
            'themeColor' => '#0ea5a4',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $resetResponse = $this->postJson('/api/theme/config/reset', [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $showResponse->assertOk()
            ->assertJsonPath('code', '1003');

        $updateResponse->assertOk()
            ->assertJsonPath('code', '1003');

        $resetResponse->assertOk()
            ->assertJsonPath('code', '1003');
    }

    public function test_super_admin_receives_same_theme_config_with_or_without_tenant_switch(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $this->putJson('/api/theme/config', [
            'themeColor' => '#4f46e5',
            'themeRadius' => 9,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('code', '0000');

        $platformScopeResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $tenantScopeResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $platformScopeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.themeConfig.themeColor', '#4f46e5')
            ->assertJsonPath('data.themeConfig.themeRadius', 9);

        $tenantScopeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.themeConfig.themeColor', '#4f46e5')
            ->assertJsonPath('data.themeConfig.themeRadius', 9);
    }

    public function test_super_admin_with_selected_tenant_cannot_access_theme_config_console(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ];

        $showResponse = $this->getJson('/api/theme/config', $headers);
        $updateResponse = $this->putJson('/api/theme/config', [
            'themeColor' => '#1f7ae0',
        ], $headers);
        $resetResponse = $this->postJson('/api/theme/config/reset', [], $headers);

        $showResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage theme configuration');

        $updateResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage theme configuration');

        $resetResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage theme configuration');
    }

    public function test_regular_user_without_theme_permission_cannot_access_theme_config_console(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $showResponse = $this->getJson('/api/theme/config', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $showResponse->assertOk()
            ->assertJsonPath('code', '1003');
    }

    public function test_guest_can_read_public_theme_config_for_login_page(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = (string) $loginResponse->json('data.token');

        $this->putJson('/api/theme/config', [
            'themeSchemaVisible' => false,
            'multilingualVisible' => false,
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('code', '0000');

        $publicResponse = $this->getJson('/api/theme/public-config');

        $publicResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.editable', false)
            ->assertJsonPath('data.config.themeSchemaVisible', false)
            ->assertJsonPath('data.config.multilingualVisible', false);
    }

    public function test_theme_profile_seeder_invalidates_stale_theme_cache(): void
    {
        Cache::put('theme.profile.platform.0', [
            'config' => [
                'themeSchemaVisible' => true,
                'globalSearchVisible' => true,
            ],
            'version' => 999,
        ], now()->addMinutes(10));

        $this->seed(ThemeProfileSeeder::class);

        $response = $this->getJson('/api/theme/public-config');

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.config.themeSchemaVisible', false)
            ->assertJsonPath('data.config.globalSearchVisible', false);
    }
}
