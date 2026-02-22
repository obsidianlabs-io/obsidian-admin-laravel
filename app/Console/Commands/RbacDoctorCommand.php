<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RbacDoctorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rbac:doctor';

    /**
     * @var string
     */
    protected $description = 'Audit RBAC tenant consistency for users, roles, and permissions';

    /**
     * @var list<string>
     */
    private const REQUIRED_SUPER_TENANT_PERMISSIONS = [
        'tenant.view',
        'tenant.manage',
    ];

    public function handle(): int
    {
        $checks = [
            'user_role_tenant_scope' => $this->findUserRoleTenantScopeIssues(),
            'admin_tenant_permission_scope' => $this->findAdminTenantPermissionScopeIssues(),
            'super_role_tenant_permissions' => $this->findSuperRoleTenantPermissionIssues(),
        ];

        $issueCount = 0;
        foreach ($checks as $issues) {
            $issueCount += count($issues);
        }

        $this->line('RBAC Doctor Report');
        $this->line(str_repeat('-', 72));

        foreach ($checks as $check => $issues) {
            $status = $issues === [] ? 'OK' : 'FAIL';
            $this->line(sprintf('%s %s (%d)', $status, $check, count($issues)));

            foreach ($issues as $issue) {
                $this->line('  - '.$issue);
            }
        }

        $this->line(str_repeat('-', 72));

        if ($issueCount === 0) {
            $this->info('RBAC tenant integrity checks passed.');

            return self::SUCCESS;
        }

        $this->error(sprintf('RBAC tenant integrity checks failed with %d issue(s).', $issueCount));

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function findUserRoleTenantScopeIssues(): array
    {
        $rows = User::query()
            ->leftJoin('roles as scoped_roles', 'scoped_roles.id', '=', 'users.role_id')
            ->select([
                'users.id as user_id',
                'users.email as user_email',
                'users.tenant_id as user_tenant_id',
                'users.role_id as user_role_id',
                'scoped_roles.id as role_id',
                'scoped_roles.code as role_code',
                'scoped_roles.tenant_id as role_tenant_id',
            ])
            ->where(function (Builder $query): void {
                $query->whereNull('users.role_id')
                    ->orWhereNull('scoped_roles.id')
                    ->orWhere(function (Builder $scope): void {
                        $scope->whereNull('users.tenant_id')
                            ->whereNotNull('scoped_roles.tenant_id');
                    })
                    ->orWhere(function (Builder $scope): void {
                        $scope->whereNotNull('users.tenant_id')
                            ->whereNull('scoped_roles.tenant_id');
                    })
                    ->orWhereColumn('users.tenant_id', '!=', 'scoped_roles.tenant_id');
            })
            ->orderBy('users.id')
            ->get();

        return $rows
            ->map(function ($row): string {
                return sprintf(
                    'user#%s email=%s user_tenant=%s role_id=%s role_code=%s role_tenant=%s',
                    (string) $row->user_id,
                    (string) $row->user_email,
                    $this->formatNullableNumber($row->user_tenant_id),
                    $this->formatNullableNumber($row->user_role_id),
                    (string) ($row->role_code ?? 'missing'),
                    $this->formatNullableNumber($row->role_tenant_id)
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function findAdminTenantPermissionScopeIssues(): array
    {
        $roles = Role::query()
            ->where('code', 'R_ADMIN')
            ->with(['permissions' => static function ($query): void {
                $query->select(['permissions.id', 'permissions.code'])
                    ->where('code', 'like', 'tenant.%');
            }])
            ->orderBy('id')
            ->get(['id', 'code', 'tenant_id']);

        $issues = [];

        foreach ($roles as $role) {
            $tenantPermissionCodes = $role->permissions
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->values()
                ->all();

            if ($tenantPermissionCodes === []) {
                continue;
            }

            $issues[] = sprintf(
                'role#%d code=%s tenant_id=%s has tenant permissions [%s]',
                (int) $role->id,
                (string) $role->code,
                $this->formatNullableNumber($role->tenant_id),
                implode(', ', $tenantPermissionCodes)
            );
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function findSuperRoleTenantPermissionIssues(): array
    {
        $issues = [];

        $existingRequiredCodes = Permission::query()
            ->whereIn('code', self::REQUIRED_SUPER_TENANT_PERMISSIONS)
            ->pluck('code')
            ->map(static fn ($code): string => (string) $code)
            ->values()
            ->all();

        $missingPermissionDefinitions = array_values(array_diff(self::REQUIRED_SUPER_TENANT_PERMISSIONS, $existingRequiredCodes));
        foreach ($missingPermissionDefinitions as $permissionCode) {
            $issues[] = sprintf('required permission definition is missing: %s', $permissionCode);
        }

        $globalSuperRoles = Role::query()
            ->where('code', 'R_SUPER')
            ->whereNull('tenant_id')
            ->with('permissions:id,code')
            ->orderBy('id')
            ->get(['id', 'code', 'tenant_id']);

        if ($globalSuperRoles->isEmpty()) {
            $issues[] = 'global R_SUPER role is missing';

            return $issues;
        }

        foreach ($globalSuperRoles as $role) {
            $assignedCodes = $role->permissions
                ->pluck('code')
                ->map(static fn ($code): string => (string) $code)
                ->values()
                ->all();

            $missingCodes = array_values(array_diff(self::REQUIRED_SUPER_TENANT_PERMISSIONS, $assignedCodes));
            if ($missingCodes === []) {
                continue;
            }

            $issues[] = sprintf(
                'role#%d code=%s tenant_id=%s missing [%s]',
                (int) $role->id,
                (string) $role->code,
                $this->formatNullableNumber($role->tenant_id),
                implode(', ', $missingCodes)
            );
        }

        return $issues;
    }

    private function formatNullableNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'null';
        }

        return (string) (int) $value;
    }
}
