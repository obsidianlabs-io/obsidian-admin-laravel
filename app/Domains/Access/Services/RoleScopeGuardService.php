<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Shared\Support\TenantVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

final class RoleScopeGuardService
{
    public const RESERVED_ROLE_CODE_SUPER = 'R_SUPER';

    /**
     * @param  Builder<Role>  $query
     */
    public function applyRoleVisibilityScope(Builder $query, ?int $tenantId, bool $isSuper): void
    {
        TenantVisibility::applyScope($query, $tenantId, $isSuper);
    }

    public function uniqueRoleCodeRule(?int $tenantId, ?int $ignoreRoleId = null): Unique
    {
        $rule = Rule::unique('roles', 'code')
            ->where(function ($query) use ($tenantId): void {
                if ($tenantId === null) {
                    $query->whereNull('tenant_id');

                    return;
                }

                $query->where('tenant_id', $tenantId);
            });

        if ($ignoreRoleId !== null) {
            $rule->ignore($ignoreRoleId);
        }

        return $rule;
    }

    public function uniqueRoleNameRule(?int $tenantId, ?int $ignoreRoleId = null): Unique
    {
        $rule = Rule::unique('roles', 'name')
            ->where(function ($query) use ($tenantId): void {
                if ($tenantId === null) {
                    $query->whereNull('tenant_id');

                    return;
                }

                $query->where('tenant_id', $tenantId);
            });

        if ($ignoreRoleId !== null) {
            $rule->ignore($ignoreRoleId);
        }

        return $rule;
    }

    /**
     * @return Builder<Permission>
     */
    public function buildAssignablePermissionQuery(?int $tenantId, bool $isSuper): Builder
    {
        $query = Permission::query()->where('status', '1');

        if (! $this->allowPlatformPermissions($tenantId, $isSuper)) {
            $query->where(function (Builder $builder): void {
                $builder->where('code', 'not like', 'permission.%')
                    ->where('code', 'not like', 'tenant.%')
                    ->where('code', 'not like', 'language.%')
                    ->where('code', 'not like', 'audit.policy.%');
            });
        }

        return $query;
    }

    /**
     * @param  list<string>  $permissionCodes
     * @return array{ok: bool, ids: list<int>}
     */
    public function resolveAssignablePermissionIds(array $permissionCodes, ?int $tenantId, bool $isSuper): array
    {
        $uniqueCodes = array_values(array_unique(array_map(static fn (string $code): string => trim($code), $permissionCodes)));
        $uniqueCodes = array_values(array_filter($uniqueCodes, static fn (string $code): bool => $code !== ''));

        if ($uniqueCodes === []) {
            return [
                'ok' => true,
                'ids' => [],
            ];
        }

        $assignablePermissions = $this->buildAssignablePermissionQuery($tenantId, $isSuper)
            ->whereIn('code', $uniqueCodes)
            ->get(['id', 'code']);

        if ($assignablePermissions->count() !== count($uniqueCodes)) {
            return [
                'ok' => false,
                'ids' => [],
            ];
        }

        /** @var list<int> $permissionIds */
        $permissionIds = $assignablePermissions
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        return [
            'ok' => true,
            'ids' => $permissionIds,
        ];
    }

    public function resolveUserRoleLevel(User $user): int
    {
        $roleId = (int) ($user->role_id ?? 0);
        if ($roleId <= 0) {
            return 0;
        }

        $level = Role::query()->whereKey($roleId)->value('level');

        return max(0, (int) ($level ?? 0));
    }

    public function canManageRoleLevel(int $actorLevel, Role $role): bool
    {
        return (int) $role->level < $actorLevel;
    }

    /**
     * @param  Builder<Role>  $query
     */
    public function applyRoleManageableFilter(
        Builder $query,
        int $actorLevel,
        ?int $tenantId,
        bool $isSuper,
        bool $manageableOnly
    ): void {
        if (! $manageableOnly) {
            $query->where('level', '<=', $actorLevel);

            return;
        }

        $query->where(function (Builder $builder) use ($actorLevel, $tenantId, $isSuper): void {
            $builder->where('level', '<', $actorLevel);

            if ($this->allowPlatformPermissions($tenantId, $isSuper)) {
                $builder->orWhere('code', self::RESERVED_ROLE_CODE_SUPER);
            }
        });
    }

    public function isRoleManageableInScope(Role $role, int $actorLevel, ?int $tenantId, bool $isSuper): bool
    {
        $roleLevel = max(0, (int) ($role->level ?? 0));
        if ($roleLevel < $actorLevel) {
            return true;
        }

        return $this->allowPlatformPermissions($tenantId, $isSuper)
            && $this->isReservedRoleCode((string) $role->code);
    }

    public function isReservedRoleCode(string $roleCode): bool
    {
        return strtoupper(trim($roleCode)) === self::RESERVED_ROLE_CODE_SUPER;
    }

    public function isRoleCodeChangeAllowed(string $requestedRoleCode, ?Role $existingRole = null): bool
    {
        if (! $this->isReservedRoleCode($requestedRoleCode)) {
            return true;
        }

        return $existingRole !== null
            && $this->isReservedRoleCode((string) $existingRole->code);
    }

    public function isRequestedRoleLevelAllowed(int $requestedLevel, int $actorLevel, int $minLevel, int $maxLevel): bool
    {
        if ($requestedLevel < $minLevel || $requestedLevel > $maxLevel) {
            return false;
        }

        return $requestedLevel < $actorLevel;
    }

    public function findRoleInScope(int $id, ?int $tenantId, bool $isSuper, bool $withUserCount = false): ?Role
    {
        $query = Role::query()->whereKey($id);
        if ($withUserCount) {
            $query->withCount('users');
        }

        $this->applyRoleVisibilityScope($query, $tenantId, $isSuper);

        return $query->first();
    }

    private function allowPlatformPermissions(?int $tenantId, bool $isSuper): bool
    {
        return $isSuper && $tenantId === null;
    }
}
