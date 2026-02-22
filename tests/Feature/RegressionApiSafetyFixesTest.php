<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegressionApiSafetyFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_platform_scope_when_no_active_tenants(): void
    {
        $this->seed();
        Tenant::query()->update(['status' => '2']);

        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/auth/getUserInfo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.currentTenantId', '')
            ->assertJsonPath('data.currentTenantName', 'No Tenants')
            ->assertJsonPath('data.menuScope', 'platform')
            ->assertJsonCount(0, 'data.tenants');
    }

    public function test_invalid_selected_tenant_is_rejected_globally_for_super_admin(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $response = $this->getJson('/api/system/feature-flags?current=1&size=5', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => '999999',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Selected tenant is invalid or inactive');
    }

    public function test_tenant_console_requires_no_tenant_scope(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();

        $response = $this->getJson('/api/tenant/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Switch to No Tenant to manage tenants');
    }

    public function test_audit_log_list_returns_request_id_field(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');
        $superUser = User::query()->where('name', 'Super')->firstOrFail();

        AuditLog::query()->create([
            'user_id' => $superUser->id,
            'tenant_id' => null,
            'action' => 'test.request-id.visible',
            'auditable_type' => 'system',
            'auditable_id' => null,
            'new_values' => ['ok' => true],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'request_id' => 'req-visible-123',
        ]);

        $response = $this->getJson('/api/audit/list?current=1&size=5&action=test.request-id.visible', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.records.0.requestId', 'req-visible-123');
    }

    public function test_permission_code_cannot_be_modified_after_creation(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');
        $permission = Permission::query()->where('code', 'user.view')->firstOrFail();

        $response = $this->putJson('/api/permission/'.$permission->id, [
            'permissionCode' => 'user.view.renamed',
            'permissionName' => (string) $permission->name,
            'group' => (string) ($permission->group ?? ''),
            'description' => (string) ($permission->description ?? ''),
            'status' => (string) $permission->status,
            'version' => (string) ($permission->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Permission code cannot be modified');
    }

    public function test_role_code_super_is_reserved_for_role_crud(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->postJson('/api/role', [
            'roleCode' => 'R_SUPER',
            'roleName' => 'Fake Super',
            'level' => 300,
            'description' => 'Should be rejected',
            'status' => '1',
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1002')
            ->assertJsonPath('msg', 'Role code is reserved');
    }

    public function test_role_level_cannot_be_raised_to_actor_level_or_above_on_update(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $userRole = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $mainTenant->id)
            ->firstOrFail();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->putJson('/api/role/'.$userRole->id, [
            'roleCode' => (string) $userRole->code,
            'roleName' => (string) $userRole->name,
            'level' => 500,
            'description' => (string) ($userRole->description ?? ''),
            'status' => (string) $userRole->status,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Role level must be lower than your current role level');
    }

    public function test_admin_cannot_update_or_delete_same_level_user(): void
    {
        $this->seed();

        $admin = User::query()->where('name', 'Admin')->firstOrFail();
        $mainTenantId = (int) $admin->tenant_id;
        $adminRole = Role::query()
            ->where('code', 'R_ADMIN')
            ->where('tenant_id', $mainTenantId)
            ->firstOrFail();

        $peerAdmin = User::query()->create([
            'name' => 'AdminPeer',
            'email' => 'admin.peer@obsidian.local',
            'password' => bcrypt('123456'),
            'status' => '1',
            'role_id' => $adminRole->id,
            'tenant_id' => $mainTenantId,
        ]);

        $token = $this->loginAndGetToken('Admin');
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenantId,
        ];

        $updateResponse = $this->putJson('/api/user/'.$peerAdmin->id, [
            'userName' => 'AdminPeerUpdated',
            'email' => 'admin.peer@obsidian.local',
            'roleCode' => 'R_ADMIN',
            'status' => '1',
        ], $headers);

        $deleteResponse = $this->deleteJson('/api/user/'.$peerAdmin->id, [], $headers);

        $updateResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $deleteResponse->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $peerAdmin->refresh();
        $this->assertSame('AdminPeer', $peerAdmin->name);
        $this->assertNull($peerAdmin->deleted_at);
    }

    public function test_admin_cannot_assign_same_level_role_to_lower_level_user(): void
    {
        $this->seed();

        $admin = User::query()->where('name', 'Admin')->firstOrFail();
        $mainTenantId = (int) $admin->tenant_id;
        $targetUser = User::query()->where('name', 'User')->where('tenant_id', $mainTenantId)->firstOrFail();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->putJson('/api/user/'.$targetUser->id.'/role', [
            'roleCode' => 'R_ADMIN',
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenantId,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_role_all_manageable_only_excludes_same_level_roles(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');
        $admin = User::query()->where('name', 'Admin')->firstOrFail();
        $tenantId = (int) $admin->tenant_id;

        $response = $this->getJson('/api/role/all?manageableOnly=1', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $tenantId,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $records = collect($response->json('data.records', []));

        $this->assertFalse($records->contains(fn (array $item): bool => ($item['roleCode'] ?? null) === 'R_ADMIN'));
        $this->assertTrue($records->contains(fn (array $item): bool => ($item['roleCode'] ?? null) === 'R_USER'));
    }

    public function test_user_list_returns_actor_level_and_manageable_flag(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');
        $admin = User::query()->where('name', 'Admin')->firstOrFail();
        $tenantId = (int) $admin->tenant_id;

        $response = $this->getJson('/api/user/list?current=1&size=10', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $tenantId,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.actorLevel', 500);

        $records = collect($response->json('data.records', []));
        $first = $records->first();

        $this->assertIsArray($first);
        $this->assertArrayHasKey('manageable', $first);
    }

    public function test_auth_custom_error_route_is_forbidden_for_non_super_admin(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('User');

        $response = $this->getJson('/api/auth/error?code=1999&msg=demo', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_feature_flag_toggle_uses_standard_response_wrapper(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $response = $this->putJson('/api/system/feature-flags/toggle', [
            'key' => 'menu.permission',
            'enabled' => false,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonPath('data.key', 'menu.permission')
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
                'traceId',
            ]);
    }

    public function test_api_permission_middleware_error_includes_trace_id(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('User');

        $response = $this->getJson('/api/audit/list?current=1&size=5', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
                'traceId',
            ]);
    }

    public function test_idempotency_conflict_returns_wrapper_with_409_status(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Super');

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => 'tenant-create-conflict-key-1',
        ];

        $first = $this->postJson('/api/tenant', [
            'tenantCode' => 'TENANT_EXTRA_A',
            'tenantName' => 'Tenant Extra A',
            'status' => '1',
        ], $headers);

        $first->assertOk()->assertJsonPath('code', '0000');

        $second = $this->postJson('/api/tenant', [
            'tenantCode' => 'TENANT_EXTRA_B',
            'tenantName' => 'Tenant Extra B',
            'status' => '1',
        ], $headers);

        $second->assertStatus(409)
            ->assertJsonPath('code', '1002')
            ->assertJsonStructure([
                'code',
                'msg',
                'data',
                'requestId',
                'traceId',
            ]);
    }

    public function test_empty_cursor_without_cursor_mode_uses_normal_pagination(): void
    {
        $this->seed();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/user/list?size=2&cursor=', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000')
            ->assertJsonMissingPath('data.paginationMode')
            ->assertJsonStructure([
                'code',
                'msg',
                'data' => ['current', 'size', 'total', 'records'],
            ]);
    }

    public function test_profile_password_update_preserves_leading_and_trailing_spaces(): void
    {
        $this->seed();

        $token = $this->loginAndGetToken('Admin');
        $newPassword = ' AdminPrime123 ';

        $response = $this->putJson('/api/auth/profile', [
            'userName' => 'Admin',
            'email' => 'admin@obsidian.local',
            'currentPassword' => '123456',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $loginWithSpaces = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => $newPassword,
        ]);

        $loginTrimmed = $this->postJson('/api/auth/login', [
            'userName' => 'Admin',
            'password' => trim($newPassword),
        ]);

        $loginWithSpaces->assertOk()
            ->assertJsonPath('code', '0000');

        $loginTrimmed->assertOk()
            ->assertJsonPath('code', '1001');
    }

    public function test_admin_update_user_password_preserves_leading_and_trailing_spaces(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $targetUser = User::query()
            ->where('name', 'User')
            ->where('tenant_id', $mainTenant->id)
            ->firstOrFail();

        $token = $this->loginAndGetToken('Super');
        $newPassword = ' TargetUser123 ';

        $response = $this->putJson('/api/user/'.$targetUser->id, [
            'userName' => (string) $targetUser->name,
            'email' => (string) $targetUser->email,
            'roleCode' => 'R_USER',
            'status' => '1',
            'password' => $newPassword,
        ], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $loginWithSpaces = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => $newPassword,
        ]);

        $loginTrimmed = $this->postJson('/api/auth/login', [
            'userName' => 'User',
            'password' => trim($newPassword),
        ]);

        $loginWithSpaces->assertOk()
            ->assertJsonPath('code', '0000');

        $loginTrimmed->assertOk()
            ->assertJsonPath('code', '1001');
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
