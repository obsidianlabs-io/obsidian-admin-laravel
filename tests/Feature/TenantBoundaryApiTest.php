<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBoundaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_tenant_admin_cannot_delete_branch_user_by_direct_url(): void
    {
        $this->seed();

        $branchUser = User::query()->where('name', 'UserBranch')->firstOrFail();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->deleteJson('/api/user/'.$branchUser->id, [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $this->assertNull(User::query()->withTrashed()->findOrFail($branchUser->id)->deleted_at);
    }

    public function test_main_tenant_admin_cannot_update_branch_user_by_direct_url(): void
    {
        $this->seed();

        $branchUser = User::query()->where('name', 'UserBranch')->firstOrFail();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->putJson('/api/user/'.$branchUser->id, [
            'userName' => $branchUser->name,
            'email' => $branchUser->email,
            'roleCode' => 'R_USER',
            'status' => '1',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_main_tenant_admin_cannot_update_branch_role_by_direct_url(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $branchRole = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $branchTenant->id)
            ->firstOrFail();

        $token = $this->loginAndGetToken('Admin');

        $response = $this->putJson('/api/role/'.$branchRole->id, [
            'roleCode' => $branchRole->code,
            'roleName' => $branchRole->name,
            'description' => (string) ($branchRole->description ?? ''),
            'status' => '1',
            'level' => (int) $branchRole->level,
            'permissionCodes' => ['user.view'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');
    }

    public function test_admin_cannot_escape_scope_with_x_tenant_header_override(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/user/list?size=100', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $records = $response->json('data.records');
        $this->assertIsArray($records);

        $emails = array_values(array_map(
            static fn (array $record): string => (string) ($record['email'] ?? ''),
            $records
        ));

        $this->assertNotContains('admin.branch@obsidian.local', $emails);
        $this->assertNotContains('user.branch@obsidian.local', $emails);
    }

    public function test_admin_cannot_leak_branch_roles_when_overriding_tenant_header(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $token = $this->loginAndGetToken('Admin');

        $response = $this->getJson('/api/role/list?size=100', [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $branchTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '0000');

        $records = $response->json('data.records');
        $this->assertIsArray($records);

        foreach ($records as $record) {
            $this->assertSame((string) $mainTenant->id, (string) ($record['tenantId'] ?? ''));
        }
    }

    public function test_database_rejects_cross_tenant_role_assignment_even_if_scope_checks_are_bypassed(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();

        $user = User::query()->where('email', 'user@obsidian.local')->firstOrFail();
        $this->assertSame((int) $mainTenant->id, (int) $user->tenant_id);

        $branchRole = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $branchTenant->id)
            ->firstOrFail();

        $this->expectException(QueryException::class);

        $user->forceFill(['role_id' => $branchRole->id])->save();
    }

    public function test_super_admin_selected_tenant_cannot_manage_other_tenant_users(): void
    {
        $this->seed();

        $mainTenant = Tenant::query()->where('code', 'TENANT_MAIN')->firstOrFail();
        $branchUser = User::query()->where('name', 'UserBranch')->firstOrFail();
        $token = $this->loginAndGetToken('Super');

        $response = $this->deleteJson('/api/user/'.$branchUser->id, [], [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-Id' => (string) $mainTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('code', '1003')
            ->assertJsonPath('msg', 'Forbidden');

        $this->assertNull(User::query()->withTrashed()->findOrFail($branchUser->id)->deleted_at);
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
