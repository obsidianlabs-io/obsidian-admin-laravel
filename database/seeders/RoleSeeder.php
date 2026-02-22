<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Tenant\Models\Tenant;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;
use RuntimeException;

class RoleSeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['roles', 'permissions', 'tenants']);
    }

    protected function module(): string
    {
        return 'rbac.roles';
    }

    /**
     * @return array<int, list<array{
     *   code: string,
     *   name: string,
     *   description: string,
     *   status: string,
     *   level: int,
     *   tenantCode: string|null,
     *   permissionSet: 'super'|'admin'|'user'
     * }>>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::roles(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{
         *   code: string,
         *   name: string,
         *   description: string,
         *   status: string,
         *   level: int,
         *   tenantCode: string|null,
         *   permissionSet: 'super'|'admin'|'user'
         * }> $roles
         */
        $roles = $payload;

        $permissionIdsByCode = Permission::query()
            ->pluck('id', 'code')
            ->mapWithKeys(static fn (mixed $id, mixed $code): array => [(string) $code => (int) $id])
            ->all();

        $permissionCodes = array_keys($permissionIdsByCode);
        $adminExcludedCodes = [
            'tenant.view',
            'tenant.manage',
            'theme.view',
            'theme.manage',
            'language.view',
            'language.manage',
            'audit.policy.view',
            'audit.policy.manage',
        ];

        $permissionIdsBySet = [
            'super' => $this->resolvePermissionIds($permissionIdsByCode, $permissionCodes),
            'admin' => $this->resolvePermissionIds($permissionIdsByCode, array_values(array_diff($permissionCodes, $adminExcludedCodes))),
            'user' => $this->resolvePermissionIds($permissionIdsByCode, ['user.view']),
        ];

        // Ensure admin/user roles are tenant-scoped only.
        Role::query()
            ->where('tenant_scope_id', 0)
            ->whereIn('code', ['R_ADMIN', 'R_USER'])
            ->forceDelete();

        $tenantIdsByCode = Tenant::query()
            ->pluck('id', 'code')
            ->mapWithKeys(static fn (mixed $id, mixed $code): array => [(string) $code => (int) $id])
            ->all();

        foreach ($roles as $roleData) {
            $tenantCode = $roleData['tenantCode'];
            $tenantId = null;

            if ($tenantCode !== null) {
                $tenantId = $tenantIdsByCode[$tenantCode] ?? null;
                if (! is_int($tenantId) || $tenantId <= 0) {
                    throw new RuntimeException("Seed tenant not found for role [{$roleData['code']}] scope [{$tenantCode}]");
                }
            }

            $scopeId = $tenantId ?? 0;

            $role = Role::query()->withTrashed()->updateOrCreate(
                [
                    'code' => $roleData['code'],
                    'tenant_scope_id' => $scopeId,
                ],
                [
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'status' => $roleData['status'],
                    'level' => (int) $roleData['level'],
                    'tenant_id' => $tenantId,
                    'deleted_at' => null,
                ]
            );

            $role->permissions()->sync($permissionIdsBySet[$roleData['permissionSet']] ?? []);
        }
    }

    /**
     * @param  array<string, int>  $permissionIdsByCode
     * @param  list<string>  $codes
     * @return list<int>
     */
    private function resolvePermissionIds(array $permissionIdsByCode, array $codes): array
    {
        $ids = [];

        foreach ($codes as $code) {
            $id = $permissionIdsByCode[$code] ?? null;
            if (! is_int($id) || $id <= 0) {
                continue;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
