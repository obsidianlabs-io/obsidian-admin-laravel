<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\TotpService;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_remember_me_and_receive_wrapped_tokens(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
            'rememberMe' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['token', 'refreshToken'],
            ]);

        $refreshToken = $response->json('data.refreshToken');
        $refreshTokenRecord = PersonalAccessToken::findToken($refreshToken);

        $this->assertNotNull($refreshTokenRecord);
        $this->assertTrue($refreshTokenRecord->can('remember-me'));
    }

    public function test_login_with_locale_updates_user_preference_locale(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'Super')->firstOrFail();
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'locale' => 'en-US',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
            'locale' => 'zh-CN',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'locale' => 'zh-CN',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed();

        $inactiveUser = User::query()->where('name', 'User')->firstOrFail();
        $inactiveUser->forceFill(['status' => '2'])->save();

        $response = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '8888');
    }

    public function test_login_is_rate_limited_after_consecutive_failed_attempts(): void
    {
        $this->seed();

        config()->set('auth.login_rate_limit.max_attempts', 2);
        config()->set('auth.login_rate_limit.decay_seconds', 120);

        $payload = [
            'userName' => 'Admin',
            'password' => 'wrong-password',
        ];

        $firstResponse = $this->postJson('/api/auth/login', $payload);
        $secondResponse = $this->postJson('/api/auth/login', $payload);
        $thirdResponse = $this->postJson('/api/auth/login', $payload);

        $firstResponse->assertOk()
            ->assertJsonPath('code', '1001')
            ->assertJsonPath('msg', 'Username or password is incorrect');
        $secondResponse->assertOk()
            ->assertJsonPath('code', '1001')
            ->assertJsonPath('msg', 'Username or password is incorrect');
        $thirdResponse->assertOk()
            ->assertJsonPath('code', '1001');

        $this->assertStringContainsString('Too many login attempts', (string) $thirdResponse->json('msg'));
    }

    public function test_super_admin_cannot_create_user_with_weak_password(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->postJson('/api/user', [
            'userName' => 'WeakPasswordUser',
            'email' => 'weak.password.user@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => '12345678',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002');

        $this->assertStringContainsString('password', strtolower((string) $response->json('msg')));
        $this->assertDatabaseMissing('users', [
            'email' => 'weak.password.user@obsidian.local',
        ]);
    }

    public function test_weak_password_validation_returns_chinese_message_when_locale_is_zh_cn(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->postJson('/api/user', [
            'userName' => 'WeakPasswordLocaleUser',
            'email' => 'weak.password.locale.user@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'Aa12345',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
            'X-Locale' => 'zh-CN',
            'Accept-Language' => 'zh-CN',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002');

        $message = (string) $response->json('msg');
        $this->assertStringContainsString('密码', $message);
        $this->assertStringContainsString('至少', $message);
    }

    public function test_authenticated_user_can_get_user_info_in_obsidian_format(): void
    {
        $this->seed();
        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'Admin')
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.timezone', 'Asia/Kuala_Lumpur')
            ->assertJsonPath('data.roles.0', 'R_ADMIN')
            ->assertJsonPath('data.userId', (string) $adminUser->id)
            ->assertJsonPath('data.buttons.0', 'user.view')
            ->assertJsonPath('data.currentTenantName', 'Main Tenant')
            ->assertJsonPath('data.tenants.0.tenantName', 'Main Tenant');
    }

    public function test_admin_user_info_includes_tenant_scoped_backend_menus_and_route_rules(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.menuScope', 'tenant')
            ->assertJsonPath('data.routeRules.tenant.noTenantOnly', true)
            ->assertJsonPath('data.routeRules.permission.noTenantOnly', true);

        $menus = $response->json('data.menus');
        $this->assertIsArray($menus);
        $topMenuKeys = array_map(
            static fn (array $menu): string => (string) ($menu['key'] ?? ''),
            array_filter($menus, static fn ($menu): bool => is_array($menu))
        );
        $this->assertContains('access-management', $topMenuKeys);
        $this->assertSame(
            'menu.accessManagement',
            $this->findMenuByKey($menus, 'access-management')['i18nKey'] ?? null
        );

        $menuRouteKeys = $this->collectMenuRouteKeys($menus);
        $this->assertContains('dashboard', $menuRouteKeys);
        $this->assertContains('user', $menuRouteKeys);
        $this->assertContains('role', $menuRouteKeys);
        $this->assertNotContains('tenant', $menuRouteKeys);
        $this->assertNotContains('permission', $menuRouteKeys);
        $this->assertNotContains('language', $menuRouteKeys);
    }

    public function test_super_admin_menu_payload_changes_by_scope(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $platformScopeResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $platformScopeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.menuScope', 'platform');

        $platformMenus = $platformScopeResponse->json('data.menus');
        $this->assertIsArray($platformMenus);
        $platformRouteKeys = $this->collectMenuRouteKeys($platformMenus);
        $this->assertContains('tenant', $platformRouteKeys);
        $this->assertContains('permission', $platformRouteKeys);
        $this->assertContains('language', $platformRouteKeys);
        $this->assertContains('theme-config', $platformRouteKeys);
        $this->assertContains('audit-policy', $platformRouteKeys);
        $this->assertContains('audit', $platformRouteKeys);
        $this->assertSame('menu.systemSettings', $this->findMenuByKey($platformMenus, 'platform-settings')['i18nKey'] ?? null);

        $tenantScopeResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $tenantScopeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.menuScope', 'tenant');

        $tenantMenus = $tenantScopeResponse->json('data.menus');
        $this->assertIsArray($tenantMenus);
        $tenantRouteKeys = $this->collectMenuRouteKeys($tenantMenus);
        $this->assertNotContains('tenant', $tenantRouteKeys);
        $this->assertNotContains('permission', $tenantRouteKeys);
        $this->assertNotContains('language', $tenantRouteKeys);
        $this->assertNotContains('theme-config', $tenantRouteKeys);
        $this->assertNotContains('audit-policy', $tenantRouteKeys);
        $this->assertContains('audit', $tenantRouteKeys);
        $this->assertContains('role', $tenantRouteKeys);
    }

    public function test_authenticated_user_can_get_menus_endpoint(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/auth/menus', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.menuScope', 'tenant')
            ->assertJsonStructure([
                'data' => [
                    'menus',
                    'routeRules',
                ],
            ]);
    }

    public function test_user_menu_and_listing_allow_manage_permission_without_view(): void
    {
        $this->seed();

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $adminRole = Role::query()->whereKey($adminUser->role_id)->firstOrFail();
        $managePermissionIds = Permission::query()
            ->whereIn('code', ['user.manage'])
            ->pluck('id')
            ->all();
        $adminRole->permissions()->sync($managePermissionIds);

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);
        $token = $loginResponse->json('data.token');

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $menuRouteKeys = $this->collectMenuRouteKeys($infoResponse->json('data.menus'));
        $this->assertContains('user', $menuRouteKeys);

        $listResponse = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $listResponse->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/auth/profile', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'Admin')
            ->assertJsonPath('data.locale', 'en-US')
            ->assertJsonPath('data.timezone', 'Asia/Kuala_Lumpur')
            ->assertJsonPath('data.roleCode', 'R_ADMIN')
            ->assertJsonPath('data.tenantName', 'Main Tenant')
            ->assertJsonPath('data.status', '1')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => [
                    'userId',
                    'userName',
                    'locale',
                    'timezone',
                    'email',
                    'roleCode',
                    'roleName',
                    'tenantId',
                    'tenantName',
                    'status',
                    'createTime',
                    'updateTime',
                ],
            ]);
    }

    public function test_authenticated_user_can_update_preferred_locale(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $updateResponse = $this->putJson('/api/auth/preferred-locale', [
            'locale' => 'en-US',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.locale', 'en-US');

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $adminUser->id,
            'locale' => 'en-US',
        ]);

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.locale', 'en-US');
    }

    public function test_authenticated_user_can_update_theme_schema_preferences(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $updateResponse = $this->putJson('/api/auth/preferences', [
            'themeSchema' => 'dark',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.themeSchema', 'dark');

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $adminUser->id,
            'theme_schema' => 'dark',
        ]);

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.themeSchema', 'dark');
    }

    public function test_authenticated_user_can_update_timezone_preferences(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $updateResponse = $this->putJson('/api/auth/preferences', [
            'timezone' => 'Asia/Kuala_Lumpur',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.timezone', 'Asia/Kuala_Lumpur');

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $adminUser->id,
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.timezone', 'Asia/Kuala_Lumpur');
    }

    public function test_authenticated_user_can_list_supported_timezones(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = (string) $loginResponse->json('data.token');

        $response = $this->getJson('/api/auth/timezones', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.defaultTimezone', 'UTC')
            ->assertJsonStructure([
                'data' => [
                    'defaultTimezone',
                    'records' => [
                        '*' => ['timezone', 'offset', 'label'],
                    ],
                ],
            ]);
    }

    /**
     * @return list<string>
     */
    private function collectMenuRouteKeys(mixed $menus): array
    {
        if (! is_array($menus)) {
            return [];
        }

        $keys = [];

        foreach ($menus as $menu) {
            if (! is_array($menu)) {
                continue;
            }

            $routeKey = trim((string) ($menu['routeKey'] ?? ''));
            if ($routeKey !== '') {
                $keys[] = $routeKey;
            }

            $children = $menu['children'] ?? [];
            foreach ($this->collectMenuRouteKeys($children) as $childKey) {
                $keys[] = $childKey;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMenuByKey(mixed $menus, string $key): ?array
    {
        if (! is_array($menus)) {
            return null;
        }

        foreach ($menus as $menu) {
            if (! is_array($menu)) {
                continue;
            }

            if ((string) ($menu['key'] ?? '') === $key) {
                return $menu;
            }

            $childMatch = $this->findMenuByKey($menu['children'] ?? [], $key);
            if ($childMatch !== null) {
                return $childMatch;
            }
        }

        return null;
    }

    public function test_authenticated_user_can_update_own_profile_and_password(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $updateResponse = $this->putJson('/api/auth/profile', [
            'userName' => 'AdminPrime',
            'email' => 'admin.prime@obsidian.local',
            'currentPassword' => '123456',
            'password' => 'AdminPrime123',
            'password_confirmation' => 'AdminPrime123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'AdminPrime')
            ->assertJsonPath('data.email', 'admin.prime@obsidian.local');

        $this->assertDatabaseHas('users', [
            'name' => 'AdminPrime',
            'email' => 'admin.prime@obsidian.local',
        ]);

        $reLoginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'AdminPrime',
            'password' => 'AdminPrime123',
        ]);

        $reLoginResponse->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_profile_update_with_wrong_current_password_is_rejected(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->putJson('/api/auth/profile', [
            'userName' => 'Admin',
            'email' => 'admin@obsidian.local',
            'currentPassword' => 'wrong-password',
            'password' => 'AdminPrime123',
            'password_confirmation' => 'AdminPrime123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Current password is incorrect');
    }

    public function test_user_can_refresh_token(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $refreshToken = $loginResponse->json('data.refreshToken');

        $response = $this->postJson('/api/auth/refreshToken', [
            'refreshToken' => $refreshToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['token', 'refreshToken'],
            ]);
    }

    public function test_authenticated_user_can_list_auth_sessions(): void
    {
        config()->set('security.auth_tokens.single_device_login', false);
        $this->seed();

        $firstLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ], [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ]);
        $secondLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ], [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        ]);

        $currentToken = (string) $secondLogin->json('data.token');

        $response = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$currentToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.singleDeviceLogin', false);

        $records = collect($response->json('data.records', []));

        $this->assertGreaterThanOrEqual(2, $records->count());
        $this->assertSame(1, $records->where('current', true)->count());
        $this->assertTrue($records->every(fn (array $item): bool => isset($item['sessionId']) && $item['sessionId'] !== ''));
        $this->assertTrue($records->every(fn (array $item): bool => array_key_exists('deviceName', $item)));
        $this->assertTrue($records->every(fn (array $item): bool => array_key_exists('lastAccessUsedAt', $item)));
        $this->assertTrue($records->contains(fn (array $item): bool => in_array(($item['browser'] ?? null), ['Chrome', 'Safari'], true)));
    }

    public function test_authenticated_user_can_revoke_non_current_session(): void
    {
        config()->set('security.auth_tokens.single_device_login', false);
        $this->seed();

        $firstLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);
        $secondLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $firstToken = (string) $firstLogin->json('data.token');
        $currentToken = (string) $secondLogin->json('data.token');

        $listResponse = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$currentToken,
        ]);

        $listResponse->assertOk()->assertJsonPath('code', '0000');
        $targetSessionId = collect($listResponse->json('data.records', []))
            ->firstWhere('current', false)['sessionId'] ?? null;

        $this->assertIsString($targetSessionId);
        $this->assertNotSame('', $targetSessionId);

        $revokeResponse = $this->deleteJson('/api/auth/sessions/'.$targetSessionId, [], [
            'Authorization' => 'Bearer '.$currentToken,
        ]);

        $revokeResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.revokedCurrentSession', false);

        $oldSessionInfoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$firstToken,
        ]);
        $currentSessionInfoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$currentToken,
        ]);

        $oldSessionInfoResponse->assertOk()->assertJsonPath('code', '8888');
        $currentSessionInfoResponse->assertOk()->assertJsonPath('code', '0000');
    }

    public function test_authenticated_user_can_update_auth_session_alias(): void
    {
        config()->set('security.auth_tokens.single_device_login', false);
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ], [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ]);

        $token = (string) $login->json('data.token');

        $listResponse = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$token,
        ]);
        $listResponse->assertOk()->assertJsonPath('code', '0000');

        $sessionId = collect($listResponse->json('data.records', []))
            ->firstWhere('current', true)['sessionId'] ?? null;

        $this->assertIsString($sessionId);
        $this->assertNotSame('', $sessionId);

        $updateResponse = $this->putJson(
            '/api/auth/sessions/'.$sessionId.'/alias',
            ['deviceAlias' => 'Office MacBook'],
            [
                'Authorization' => 'Bearer '.$token,
                'Idempotency-Key' => 'session-alias-'.$sessionId,
            ]
        );

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.sessionId', $sessionId)
            ->assertJsonPath('data.deviceAlias', 'Office MacBook');

        $verifyResponse = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $verifyResponse->assertOk()->assertJsonPath('code', '0000');

        $updatedCurrent = collect($verifyResponse->json('data.records', []))
            ->firstWhere('current', true);

        $this->assertIsArray($updatedCurrent);
        $this->assertSame('Office MacBook', (string) ($updatedCurrent['deviceAlias'] ?? ''));
    }

    public function test_auth_session_alias_is_preserved_after_refresh_token_rotation(): void
    {
        config()->set('security.auth_tokens.single_device_login', true);
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ], [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ]);

        $accessToken = (string) $login->json('data.token');
        $refreshToken = (string) $login->json('data.refreshToken');

        $listBefore = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$accessToken,
        ]);
        $listBefore->assertOk()->assertJsonPath('code', '0000');
        $sessionId = collect($listBefore->json('data.records', []))
            ->firstWhere('current', true)['sessionId'] ?? null;

        $this->assertIsString($sessionId);
        $this->assertNotSame('', $sessionId);

        $this->putJson(
            '/api/auth/sessions/'.$sessionId.'/alias',
            ['deviceAlias' => 'Ops Laptop'],
            [
                'Authorization' => 'Bearer '.$accessToken,
                'Idempotency-Key' => 'alias-preserve-'.$sessionId,
            ]
        )->assertOk()->assertJsonPath('code', '0000');

        $refresh = $this->postJson('/api/auth/refreshToken', [
            'refreshToken' => $refreshToken,
        ], [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        ]);

        $refresh->assertOk()->assertJsonPath('code', '0000');

        $newAccessToken = (string) $refresh->json('data.token');
        $listAfter = $this->getJson('/api/auth/sessions', [
            'Authorization' => 'Bearer '.$newAccessToken,
        ]);

        $listAfter->assertOk()->assertJsonPath('code', '0000');

        $currentSession = collect($listAfter->json('data.records', []))
            ->firstWhere('current', true);

        $this->assertIsArray($currentSession);
        $this->assertSame($sessionId, (string) ($currentSession['sessionId'] ?? ''));
        $this->assertSame('Ops Laptop', (string) ($currentSession['deviceAlias'] ?? ''));
    }

    public function test_logout_revokes_all_tokens_in_current_session_after_refresh_rotation(): void
    {
        config()->set('security.auth_tokens.single_device_login', false);
        $this->seed();

        $login = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $firstAccessToken = (string) $login->json('data.token');
        $firstRefreshToken = (string) $login->json('data.refreshToken');

        $refresh = $this->postJson('/api/auth/refreshToken', [
            'refreshToken' => $firstRefreshToken,
        ]);

        $refresh->assertOk()->assertJsonPath('code', '0000');

        $rotatedAccessToken = (string) $refresh->json('data.token');
        $rotatedRefreshToken = (string) $refresh->json('data.refreshToken');

        $logout = $this->postJson('/api/auth/logout', [
            'refreshToken' => $rotatedRefreshToken,
        ], [
            'Authorization' => 'Bearer '.$rotatedAccessToken,
        ]);

        $logout->assertOk()->assertJsonPath('code', '0000');

        $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$firstAccessToken,
        ])->assertOk()->assertJsonPath('code', '8888');

        $this->postJson('/api/auth/refreshToken', [
            'refreshToken' => $rotatedRefreshToken,
        ])->assertOk()->assertJsonPath('code', '8888');
    }

    public function test_authenticated_user_can_get_user_list(): void
    {
        $this->seed();

        $globalRoleId = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->value('id');
        User::query()->create([
            'name' => 'GlobalSupport',
            'email' => 'global.support@obsidian.local',
            'password' => 'GlobalSupport123',
            'status' => '1',
            'role_id' => $globalRoleId,
            'tenant_id' => null,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.current', 1)
            ->assertJsonPath('data.size', 10)
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['current', 'size', 'total', 'records'],
            ]);

        $records = $response->json('data.records');
        $userNames = array_column($records, 'userName');
        $roleCodes = array_column($records, 'roleCode');

        $this->assertNotContains('Super', $userNames);
        $this->assertContains('GlobalSupport', $userNames);
        $this->assertContains('R_SUPER', $roleCodes);
        $this->assertNotContains('Admin', $userNames);
    }

    public function test_super_admin_can_switch_tenant_scope_in_user_list(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $globalRoleId = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->value('id');
        User::query()->create([
            'name' => 'GlobalOps',
            'email' => 'global.ops@obsidian.local',
            'password' => 'GlobalOps123',
            'status' => '1',
            'role_id' => $globalRoleId,
            'tenant_id' => null,
        ]);
        $userRoleId = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $branchTenant->id)
            ->value('id');

        User::query()->create([
            'name' => 'BranchUser',
            'email' => 'branch.user@obsidian.local',
            'password' => '123456',
            'status' => '1',
            'role_id' => $userRoleId,
            'tenant_id' => $branchTenant->id,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $noTenantResponse = $this->getJson('/api/user/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $mainTenantResponse = $this->getJson('/api/user/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $branchTenantResponse = $this->getJson('/api/user/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $noTenantResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1);
        $this->assertContains('GlobalOps', array_column($noTenantResponse->json('data.records'), 'userName'));
        $this->assertNotContains('BranchUser', array_column($noTenantResponse->json('data.records'), 'userName'));

        $mainTenantResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 2);
        $this->assertNotContains('BranchUser', array_column($mainTenantResponse->json('data.records'), 'userName'));

        $branchTenantResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 3);
        $this->assertContains('BranchUser', array_column($branchTenantResponse->json('data.records'), 'userName'));
    }

    public function test_admin_cannot_see_super_admin_in_user_list(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1);

        $this->assertNotContains('Super', array_column($response->json('data.records'), 'userName'));
    }

    public function test_user_cannot_see_admin_or_super_admin_in_user_list(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'User')->firstOrFail();
        $user->forceFill(['status' => '1'])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.records', []);
    }

    public function test_user_list_can_be_filtered_by_status(): void
    {
        $this->seed();

        $inactiveUser = User::query()->where('name', 'User')->firstOrFail();
        $inactiveUser->forceFill(['status' => '2'])->save();
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/user/list?current=1&size=10&status=2', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.userName', 'User')
            ->assertJsonPath('data.records.0.status', '2');
    }

    public function test_user_list_can_be_filtered_by_role_code(): void
    {
        $this->seed();
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/user/list?current=1&size=10&roleCode=R_ADMIN', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.roleCode', 'R_ADMIN');

        $roleCodes = array_unique(array_column($response->json('data.records'), 'roleCode'));
        $this->assertSame(['R_ADMIN'], array_values($roleCodes));
    }

    public function test_user_without_permission_cannot_access_role_and_permission_list(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'User')->firstOrFail();
        $user->forceFill(['status' => '1'])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $roleResponse = $this->getJson('/api/role/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $permissionResponse = $this->getJson('/api/permission/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $permissionResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_user_view_permission_can_access_role_all(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'User')->firstOrFail();
        $user->forceFill(['status' => '1'])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/role/all', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['records'],
            ]);
    }

    public function test_user_manage_permission_can_access_role_all_but_not_role_list(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'User')->firstOrFail();
        $userTenantId = $user->tenant_id ? (int) $user->tenant_id : null;

        $role = Role::query()->create([
            'code' => 'R_USER_MANAGER',
            'name' => 'User Manager',
            'description' => 'Can manage users only',
            'status' => '1',
            'tenant_id' => $userTenantId,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('code', ['user.view', 'user.manage'])
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);

        $user->forceFill([
            'status' => '1',
            'role_id' => $role->id,
        ])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $roleAllResponse = $this->getJson('/api/role/all', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleListResponse = $this->getJson('/api/role/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleAllResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['records'],
            ]);

        $roleListResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_super_admin_can_list_roles_and_permissions(): void
    {
        $this->seed();
        $expectedPermissionTotal = Permission::query()->count();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $roleResponse = $this->getJson('/api/role/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $permissionResponse = $this->getJson('/api/permission/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $roleResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', 1);

        $permissionResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.total', $expectedPermissionTotal);
    }

    public function test_super_admin_with_selected_tenant_cannot_access_permission_console(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/permission/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage permissions');
    }

    public function test_super_admin_with_selected_tenant_cannot_manage_global_role(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $globalSuperRole = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->putJson('/api/role/'.$globalSuperRole->id, [
            'roleCode' => $globalSuperRole->code,
            'roleName' => $globalSuperRole->name,
            'description' => (string) $globalSuperRole->description,
            'status' => (string) $globalSuperRole->status,
            'level' => (int) $globalSuperRole->level,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_admin_can_get_assignable_permissions_without_platform_permissions(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/role/assignable-permissions', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $permissionCodes = array_column($response->json('data.records'), 'permissionCode');

        $this->assertContains('user.view', $permissionCodes);
        $this->assertContains('role.view', $permissionCodes);
        $this->assertNotContains('permission.view', $permissionCodes);
        $this->assertNotContains('permission.manage', $permissionCodes);
        $this->assertNotContains('tenant.view', $permissionCodes);
        $this->assertNotContains('tenant.manage', $permissionCodes);
        $this->assertNotContains('language.view', $permissionCodes);
        $this->assertNotContains('language.manage', $permissionCodes);
        $this->assertNotContains('audit.policy.view', $permissionCodes);
        $this->assertNotContains('audit.policy.manage', $permissionCodes);
    }

    public function test_super_admin_without_tenant_can_get_platform_permissions_as_assignable(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/role/assignable-permissions', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $permissionCodes = array_column($response->json('data.records'), 'permissionCode');

        $this->assertContains('permission.view', $permissionCodes);
        $this->assertContains('permission.manage', $permissionCodes);
        $this->assertContains('tenant.view', $permissionCodes);
        $this->assertContains('tenant.manage', $permissionCodes);
        $this->assertContains('language.view', $permissionCodes);
        $this->assertContains('language.manage', $permissionCodes);
        $this->assertContains('audit.policy.view', $permissionCodes);
        $this->assertContains('audit.policy.manage', $permissionCodes);
    }

    public function test_super_admin_with_selected_tenant_cannot_get_platform_permissions_as_assignable(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/role/assignable-permissions', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $permissionCodes = array_column($response->json('data.records'), 'permissionCode');

        $this->assertContains('user.view', $permissionCodes);
        $this->assertContains('role.view', $permissionCodes);
        $this->assertNotContains('permission.view', $permissionCodes);
        $this->assertNotContains('permission.manage', $permissionCodes);
        $this->assertNotContains('tenant.view', $permissionCodes);
        $this->assertNotContains('tenant.manage', $permissionCodes);
        $this->assertNotContains('language.view', $permissionCodes);
        $this->assertNotContains('language.manage', $permissionCodes);
        $this->assertNotContains('audit.policy.view', $permissionCodes);
        $this->assertNotContains('audit.policy.manage', $permissionCodes);
    }

    public function test_admin_cannot_assign_platform_permissions_when_creating_or_updating_role(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $createForbiddenResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_ADMIN_FORBIDDEN_ASSIGN',
            'roleName' => 'Admin Forbidden Assign',
            'description' => 'Should fail',
            'status' => '1',
            'level' => 300,
            'permissionCodes' => ['user.view', 'permission.manage'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createForbiddenResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Some permissions are not assignable in current tenant scope');

        $createAllowedResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_ADMIN_ALLOWED_ASSIGN',
            'roleName' => 'Admin Allowed Assign',
            'description' => 'Should pass',
            'status' => '1',
            'level' => 300,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createAllowedResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $roleId = (int) $createAllowedResponse->json('data.id');

        $updateForbiddenResponse = $this->putJson('/api/role/'.$roleId, [
            'roleCode' => 'R_ADMIN_ALLOWED_ASSIGN',
            'roleName' => 'Admin Allowed Assign',
            'description' => 'Try forbidden update',
            'status' => '1',
            'level' => 300,
            'permissionCodes' => ['user.view', 'tenant.manage'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateForbiddenResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Some permissions are not assignable in current tenant scope');
    }

    public function test_super_admin_can_create_tenant_role_and_role_list_is_tenant_scoped(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $createResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_BRANCH_EDITOR',
            'roleName' => 'Branch Editor',
            'description' => 'Branch scoped role',
            'status' => '1',
            'level' => 300,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.roleCode', 'R_BRANCH_EDITOR');

        $globalRoleListResponse = $this->getJson('/api/role/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $branchRoleListResponse = $this->getJson('/api/role/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $globalCodes = array_column($globalRoleListResponse->json('data.records'), 'roleCode');
        $branchCodes = array_column($branchRoleListResponse->json('data.records'), 'roleCode');

        $globalRoleListResponse->assertOk()
            ->assertJsonPath('code', '0000');
        $this->assertNotContains('R_BRANCH_EDITOR', $globalCodes);

        $branchRoleListResponse->assertOk()
            ->assertJsonPath('code', '0000');
        $this->assertContains('R_BRANCH_EDITOR', $branchCodes);
    }

    public function test_admin_cannot_manage_global_role_but_can_manage_tenant_role(): void
    {
        $this->seed();

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $globalRole = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $updateGlobalResponse = $this->putJson('/api/role/'.$globalRole->id, [
            'roleCode' => $globalRole->code,
            'roleName' => $globalRole->name,
            'description' => $globalRole->description,
            'status' => $globalRole->status,
            'level' => (int) $globalRole->level,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateGlobalResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $createTenantRoleResponse = $this->postJson('/api/role', [
            'roleCode' => 'R_ADMIN_TENANT_EDITOR',
            'roleName' => 'Admin Tenant Editor',
            'description' => 'Tenant role',
            'status' => '1',
            'level' => 300,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createTenantRoleResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.roleCode', 'R_ADMIN_TENANT_EDITOR');

        $tenantRoleId = (int) $createTenantRoleResponse->json('data.id');

        $updateTenantRoleResponse = $this->putJson('/api/role/'.$tenantRoleId, [
            'roleCode' => 'R_ADMIN_TENANT_EDITOR',
            'roleName' => 'Admin Tenant Editor Updated',
            'description' => 'Tenant role updated',
            'status' => '2',
            'level' => 300,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateTenantRoleResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.roleName', 'Admin Tenant Editor Updated');

        $this->assertDatabaseHas('roles', [
            'id' => $tenantRoleId,
            'tenant_id' => $adminUser->tenant_id,
            'name' => 'Admin Tenant Editor Updated',
        ]);
    }

    public function test_admin_can_view_same_level_role_but_cannot_update_it(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $adminRole = Role::query()
            ->where('code', 'R_ADMIN')
            ->where('tenant_id', $mainTenant->id)
            ->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $listResponse = $this->getJson('/api/role/list?current=1&size=20', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $listResponse->assertOk()
            ->assertJsonPath('code', '0000');
        $listResponse->assertJsonPath('data.actorLevel', 500);

        $roleCodes = array_column($listResponse->json('data.records'), 'roleCode');
        $this->assertContains('R_ADMIN', $roleCodes);
        $sameLevelRole = collect($listResponse->json('data.records'))
            ->firstWhere('roleCode', 'R_ADMIN');
        $this->assertNotNull($sameLevelRole);
        $this->assertFalse((bool) ($sameLevelRole['manageable'] ?? true));

        $updateResponse = $this->putJson('/api/role/'.$adminRole->id, [
            'roleCode' => $adminRole->code,
            'roleName' => 'Admin Should Not Update Same Level',
            'description' => (string) ($adminRole->description ?? ''),
            'status' => (string) $adminRole->status,
            'level' => (int) $adminRole->level,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_role_list_can_be_filtered_by_level(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/role/list?current=1&size=50&level=100', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $records = $response->json('data.records');
        $this->assertIsArray($records);
        $this->assertNotEmpty($records);

        foreach ($records as $record) {
            $this->assertSame(100, (int) ($record['level'] ?? 0));
        }
    }

    public function test_user_without_permission_cannot_access_tenant_list(): void
    {
        $this->seed();

        $user = User::query()->where('name', 'User')->firstOrFail();
        $user->forceFill(['status' => '1'])->save();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->getJson('/api/tenant/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_admin_default_role_has_no_tenant_permissions_and_cannot_access_tenant_routes(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $buttons = $infoResponse->json('data.buttons');
        $this->assertIsArray($buttons);
        $this->assertNotContains('tenant.view', $buttons);
        $this->assertNotContains('tenant.manage', $buttons);

        $tenantListResponse = $this->getJson('/api/tenant/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $tenantListResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_non_super_user_with_tenant_permission_still_cannot_access_tenant_routes(): void
    {
        $this->seed();

        $adminUser = User::query()->where('name', 'Admin')->firstOrFail();
        $adminRole = Role::query()->whereKey($adminUser->role_id)->firstOrFail();
        $tenantPermissionIds = Permission::query()
            ->whereIn('code', ['tenant.view', 'tenant.manage'])
            ->pluck('id')
            ->all();
        $adminRole->permissions()->syncWithoutDetaching($tenantPermissionIds);

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000');
        $buttons = $infoResponse->json('data.buttons');
        $this->assertIsArray($buttons);
        $this->assertContains('tenant.view', $buttons);

        $tenantListResponse = $this->getJson('/api/tenant/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $tenantListResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_super_admin_user_info_contains_tenant_permissions(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $infoResponse = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $infoResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $buttons = $infoResponse->json('data.buttons');
        $this->assertIsArray($buttons);
        $this->assertContains('tenant.view', $buttons);
        $this->assertContains('tenant.manage', $buttons);
    }

    public function test_super_admin_can_create_update_and_delete_tenant(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $createResponse = $this->postJson('/api/tenant', [
            'tenantCode' => 'TENANT_TEST',
            'tenantName' => 'Test Tenant',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.tenantCode', 'TENANT_TEST');

        $tenantId = (int) $createResponse->json('data.id');

        $updateResponse = $this->putJson('/api/tenant/'.$tenantId, [
            'tenantCode' => 'TENANT_TEST2',
            'tenantName' => 'Test Tenant 2',
            'status' => '2',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.tenantCode', 'TENANT_TEST2')
            ->assertJsonPath('data.status', '2');

        $deleteResponse = $this->deleteJson('/api/tenant/'.$tenantId, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $deleteResponse->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_assigning_role_to_user_keeps_single_role(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $user = User::query()->where('name', 'User')->firstOrFail();
        $adminRole = Role::query()
            ->where('code', 'R_ADMIN')
            ->where('tenant_id', $mainTenant->id)
            ->firstOrFail();

        $response = $this->putJson('/api/user/'.$user->id.'/role', [
            'roleCode' => 'R_ADMIN',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.roleCode', 'R_ADMIN');

        $user->refresh();

        $this->assertSame($adminRole->id, $user->role_id);
    }

    public function test_cannot_assign_inactive_role_to_user(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');
        $user = User::query()->where('name', 'User')->firstOrFail();
        $inactiveRole = Role::query()
            ->where('code', 'R_ADMIN')
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();
        $inactiveRole->forceFill(['status' => '2'])->save();

        $response = $this->putJson('/api/user/'.$user->id.'/role', [
            'roleCode' => 'R_ADMIN',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $user->tenant_id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Role is inactive');

        $user->refresh();

        $this->assertNotSame($inactiveRole->id, $user->role_id);
    }

    public function test_super_admin_can_create_update_and_delete_user(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'CrudUser',
            'email' => 'crud.user@obsidian.local',
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => 'CrudUser123',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'CrudUser');

        $createdUserId = (int) $createResponse->json('data.userId');

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'name' => 'CrudUser',
            'email' => 'crud.user@obsidian.local',
            'status' => '1',
        ]);

        $updateResponse = $this->putJson('/api/user/'.$createdUserId, [
            'userName' => 'CrudUserUpdated',
            'email' => 'crud.user.updated@obsidian.local',
            'roleCode' => 'R_ADMIN',
            'status' => '2',
            'password' => 'CrudUser456',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.userName', 'CrudUserUpdated')
            ->assertJsonPath('data.roleCode', 'R_ADMIN')
            ->assertJsonPath('data.status', '2');

        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'name' => 'CrudUserUpdated',
            'email' => 'crud.user.updated@obsidian.local',
            'status' => '2',
        ]);

        $deleteResponse = $this->deleteJson('/api/user/'.$createdUserId, [], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $deleteResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $this->assertSoftDeleted('users', [
            'id' => $createdUserId,
        ]);
    }

    public function test_super_admin_without_selected_tenant_can_create_platform_super_admin_user(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'PlatformSuperUser',
            'email' => 'platform.super.user@obsidian.local',
            'roleCode' => 'R_SUPER',
            'status' => '1',
            'password' => 'PlatformSuper123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.roleCode', 'R_SUPER');

        $this->assertDatabaseHas('users', [
            'name' => 'PlatformSuperUser',
            'email' => 'platform.super.user@obsidian.local',
            'tenant_id' => null,
        ]);
    }

    public function test_super_admin_cannot_assign_global_role_to_tenant_user(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $globalRole = Role::query()->create([
            'code' => 'R_GLOBAL_TEST',
            'name' => 'Global Test Role',
            'description' => 'Global role for mismatch test',
            'status' => '1',
            'level' => 10,
            'tenant_id' => null,
        ]);

        $targetUser = User::query()->where('name', 'User')->firstOrFail();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $token = $loginResponse->json('data.token');

        $assignResponse = $this->putJson('/api/user/'.$targetUser->id.'/role', [
            'roleCode' => $globalRole->code,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $assignResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Role does not belong to user tenant');

        $createResponse = $this->postJson('/api/user', [
            'userName' => 'TenantUserMismatch',
            'email' => 'tenant.user.mismatch@obsidian.local',
            'roleCode' => $globalRole->code,
            'status' => '1',
            'password' => 'TenantUser123',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Role does not belong to selected tenant');

        $updateResponse = $this->putJson('/api/user/'.$targetUser->id, [
            'userName' => $targetUser->name,
            'email' => $targetUser->email,
            'roleCode' => $globalRole->code,
            'status' => $targetUser->status,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Role does not belong to user tenant');
    }

    public function test_forgot_password_endpoint_returns_success_wrapper(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'admin@obsidian.local',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');
    }

    public function test_super_admin_login_requires_otp_when_two_factor_enabled(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = $loginResponse->json('data.token');

        $setupResponse = $this->postJson('/api/auth/2fa/setup', [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $setupResponse->assertOk()->assertJsonPath('code', '0000');
        $secret = (string) $setupResponse->json('data.secret');

        /** @var TotpService $totpService */
        $totpService = app(TotpService::class);
        $otpCode = $totpService->currentCode($secret);

        $enableResponse = $this->postJson('/api/auth/2fa/enable', [
            'otpCode' => $otpCode,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $enableResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.enabled', true);

        $otpMissingLoginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);

        $otpMissingLoginResponse->assertOk()
            ->assertJsonPath('code', '4020')
            ->assertJsonPath('msg', 'Two-factor code required');

        $otpLoginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
            'otpCode' => $totpService->codeForOffset($secret, 1),
        ]);

        $otpLoginResponse->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['token', 'refreshToken'],
            ]);
    }

    public function test_totp_code_cannot_be_replayed_for_login_within_valid_window(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $token = $loginResponse->json('data.token');

        $setupResponse = $this->postJson('/api/auth/2fa/setup', [], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $setupResponse->assertOk()->assertJsonPath('code', '0000');
        $secret = (string) $setupResponse->json('data.secret');

        /** @var TotpService $totpService */
        $totpService = app(TotpService::class);

        $enableResponse = $this->postJson('/api/auth/2fa/enable', [
            'otpCode' => $totpService->currentCode($secret),
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);
        $enableResponse->assertOk()->assertJsonPath('code', '0000');

        $replayCandidateCode = $totpService->codeForOffset($secret, 1);

        $firstOtpLoginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
            'otpCode' => $replayCandidateCode,
        ]);

        $secondOtpLoginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
            'otpCode' => $replayCandidateCode,
        ]);

        $firstOtpLoginResponse->assertOk()
            ->assertJsonPath('code', '0000');

        $secondOtpLoginResponse->assertOk()
            ->assertJsonPath('code', '1001')
            ->assertJsonPath('msg', 'Two-factor code is invalid');
    }

    public function test_login_keeps_existing_tokens_when_single_device_login_is_disabled(): void
    {
        config()->set('security.auth_tokens.single_device_login', false);

        $this->seed();

        $firstLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);
        $secondLogin = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => '123456',
        ]);

        $firstToken = (string) $firstLogin->json('data.token');
        $secondToken = (string) $secondLogin->json('data.token');

        $firstInfo = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$firstToken,
        ]);
        $secondInfo = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$secondToken,
        ]);

        $firstInfo->assertOk()->assertJsonPath('code', '0000');
        $secondInfo->assertOk()->assertJsonPath('code', '0000');
    }
}
