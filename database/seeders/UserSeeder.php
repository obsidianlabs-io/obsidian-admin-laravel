<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\Tenant\Models\Tenant;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;
use RuntimeException;

class UserSeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['users', 'roles', 'tenants', 'user_preferences']);
    }

    protected function module(): string
    {
        return 'identity.users';
    }

    /**
     * @return array<int, list<array{
     *   name: string,
     *   email: string,
     *   password: string,
     *   status: string,
     *   roleCode: string,
     *   tenantCode: string|null
     * }>>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::users(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{
         *   name: string,
         *   email: string,
         *   password: string,
         *   status: string,
         *   roleCode: string,
         *   tenantCode: string|null
         * }> $seedUsers
         */
        $seedUsers = $payload;

        $tenantIdsByCode = Tenant::query()
            ->pluck('id', 'code')
            ->mapWithKeys(static fn (mixed $id, mixed $code): array => [(string) $code => (int) $id])
            ->all();

        $roleIdsByScopeCode = Role::query()
            ->select(['id', 'code', 'tenant_scope_id'])
            ->get()
            ->mapWithKeys(static fn (Role $role): array => [
                sprintf('%d:%s', (int) $role->tenant_scope_id, (string) $role->code) => (int) $role->id,
            ])
            ->all();

        foreach ($seedUsers as $seedUser) {
            $tenantCode = $seedUser['tenantCode'];
            $tenantId = null;

            if ($tenantCode !== null) {
                $tenantId = $tenantIdsByCode[$tenantCode] ?? null;
                if (! is_int($tenantId) || $tenantId <= 0) {
                    throw new RuntimeException("Seed tenant not found for user [{$seedUser['email']}]");
                }
            }

            $scopeId = $tenantId ?? 0;
            $roleKey = sprintf('%d:%s', $scopeId, $seedUser['roleCode']);
            $roleId = $roleIdsByScopeCode[$roleKey] ?? null;

            if (! is_int($roleId) || $roleId <= 0) {
                throw new RuntimeException("Seed role not found for user [{$seedUser['email']}] with scope [{$scopeId}]");
            }

            $user = User::query()->withTrashed()->updateOrCreate(
                ['email' => $seedUser['email']],
                [
                    'name' => $seedUser['name'],
                    'password' => $seedUser['password'],
                    'status' => $seedUser['status'],
                    'role_id' => $roleId,
                    'tenant_id' => $tenantId,
                    'tenant_scope_id' => $scopeId,
                    'deleted_at' => null,
                ]
            );

            UserPreference::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'locale' => SeedCatalog::defaultLocale(),
                    'timezone' => SeedCatalog::DEFAULT_TIMEZONE,
                ]
            );
        }

        $this->assertUserRoleTenantIntegrity();
    }

    private function assertUserRoleTenantIntegrity(): void
    {
        $invalidUsers = User::query()
            ->with('role:id,tenant_id')
            ->get()
            ->filter(static function (User $user): bool {
                $role = $user->role;
                if (! $role instanceof Role) {
                    return true;
                }

                $userTenantId = $user->tenant_id;
                $roleTenantId = $role->tenant_id;

                if ($userTenantId === null) {
                    return $roleTenantId !== null;
                }

                return (int) $roleTenantId !== (int) $userTenantId;
            })
            ->map(static function (User $user): string {
                $roleTenantId = $user->role instanceof Role ? $user->role->tenant_id : null;

                return sprintf(
                    '%s(email=%s,user_tenant=%s,role_tenant=%s)',
                    $user->name,
                    $user->email,
                    $user->tenant_id === null ? 'null' : (string) $user->tenant_id,
                    $roleTenantId === null ? 'null' : (string) $roleTenantId
                );
            })
            ->values()
            ->all();

        if ($invalidUsers !== []) {
            throw new RuntimeException(
                'Seeded users have invalid tenant-role scope: '.implode('; ', $invalidUsers)
            );
        }
    }
}
