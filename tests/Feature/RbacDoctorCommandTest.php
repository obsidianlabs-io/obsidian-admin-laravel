<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacDoctorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_doctor_passes_on_seeded_data(): void
    {
        $this->seed();

        $this->artisan('rbac:doctor')
            ->expectsOutputToContain('OK user_role_tenant_scope (0)')
            ->expectsOutputToContain('OK admin_tenant_permission_scope (0)')
            ->expectsOutputToContain('OK super_role_tenant_permissions (0)')
            ->expectsOutputToContain('RBAC tenant integrity checks passed.')
            ->assertExitCode(0);
    }

    public function test_rbac_doctor_detects_user_role_tenant_mismatch(): void
    {
        $this->seed();

        $branchTenant = Tenant::query()->where('code', 'TENANT_BRANCH')->firstOrFail();
        $branchRole = Role::query()
            ->where('code', 'R_USER')
            ->where('tenant_id', $branchTenant->id)
            ->firstOrFail();
        $mainUser = User::query()->where('name', 'User')->firstOrFail();

        try {
            DB::table('users')->where('id', $mainUser->id)->update([
                'role_id' => $branchRole->id,
                'tenant_scope_id' => (int) $branchTenant->id,
            ]);
        } catch (QueryException) {
            $this->markTestSkipped('DB tenant constraints already prevent role-tenant mismatches.');
        }

        $this->artisan('rbac:doctor')
            ->expectsOutputToContain('FAIL user_role_tenant_scope (1)')
            ->expectsOutputToContain('user#'.$mainUser->id)
            ->expectsOutputToContain('RBAC tenant integrity checks failed with 1 issue(s).')
            ->assertExitCode(1);
    }

    public function test_rbac_doctor_detects_admin_role_with_tenant_permissions(): void
    {
        $this->seed();

        $adminRole = Role::query()
            ->where('code', 'R_ADMIN')
            ->whereNotNull('tenant_id')
            ->firstOrFail();
        $tenantPermissionId = Permission::query()
            ->where('code', 'tenant.view')
            ->value('id');

        $this->assertNotNull($tenantPermissionId);

        $adminRole->permissions()->syncWithoutDetaching([(int) $tenantPermissionId]);

        $this->artisan('rbac:doctor')
            ->expectsOutputToContain('FAIL admin_tenant_permission_scope (1)')
            ->expectsOutputToContain('role#'.$adminRole->id)
            ->expectsOutputToContain('RBAC tenant integrity checks failed with 1 issue(s).')
            ->assertExitCode(1);
    }

    public function test_rbac_doctor_detects_global_super_role_missing_tenant_permissions(): void
    {
        $this->seed();

        $superRole = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->firstOrFail();
        $tenantPermissionIds = Permission::query()
            ->whereIn('code', ['tenant.view', 'tenant.manage'])
            ->pluck('id')
            ->all();

        $superRole->permissions()->detach($tenantPermissionIds);

        $this->artisan('rbac:doctor')
            ->expectsOutputToContain('FAIL super_role_tenant_permissions (1)')
            ->expectsOutputToContain('role#'.$superRole->id)
            ->expectsOutputToContain('RBAC tenant integrity checks failed with 1 issue(s).')
            ->assertExitCode(1);
    }
}
