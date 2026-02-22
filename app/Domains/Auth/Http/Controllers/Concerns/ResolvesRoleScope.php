<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Concerns;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ResolvesRoleScope
{
    /**
     * @return array{ok: bool, msg: string, role?: Role}
     */
    protected function findActiveRoleByCode(string $roleCode, ?int $tenantId = null, ?int $fallbackTenantId = null): array
    {
        $scopedQuery = Role::query()->where('code', $roleCode);
        $this->applyRoleTenantScope($scopedQuery, $tenantId, $fallbackTenantId);

        $role = (clone $scopedQuery)
            ->where('status', '1')
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->first();

        if (! $role) {
            $inactiveRoleExists = (clone $scopedQuery)
                ->where('status', '!=', '1')
                ->exists();

            return [
                'ok' => false,
                'msg' => $inactiveRoleExists ? 'Role is inactive' : 'Role not found',
            ];
        }

        return [
            'ok' => true,
            'msg' => 'ok',
            'role' => $role,
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    protected function applyRoleTenantScope(Builder $query, ?int $tenantId, ?int $fallbackTenantId): void
    {
        $tenantIds = [];
        foreach ([$tenantId, $fallbackTenantId] as $candidateTenantId) {
            $id = (int) ($candidateTenantId ?? 0);
            if ($id > 0 && ! in_array($id, $tenantIds, true)) {
                $tenantIds[] = $id;
            }
        }

        if ($tenantIds === []) {
            $query->whereNull('tenant_id');

            return;
        }

        $query->where(function (Builder $builder) use ($tenantIds): void {
            $builder->whereNull('tenant_id')
                ->orWhereIn('tenant_id', $tenantIds);
        });
    }

    protected function resolveUserRoleLevel(User $user): int
    {
        $user->loadMissing('role:id,level');
        $role = $user->role;

        return $this->resolveRoleLevel($role instanceof Role ? $role : null);
    }

    protected function resolveRoleLevel(?Role $role): int
    {
        return $role instanceof Role ? max(0, (int) $role->level) : 0;
    }

    protected function isRoleLevelAllowed(int $actorLevel, ?Role $targetRole): bool
    {
        if ($actorLevel <= 0 || ! $targetRole instanceof Role) {
            return false;
        }

        $targetLevel = $this->resolveRoleLevel($targetRole);
        if ($targetLevel < $actorLevel) {
            return true;
        }

        return $targetLevel === $actorLevel && (string) $targetRole->code === 'R_SUPER';
    }

    protected function isUserLevelAllowed(int $actorLevel, User $targetUser): bool
    {
        $targetUser->loadMissing('role:id,level');
        $role = $targetUser->role;

        return $actorLevel > 0 && $this->resolveRoleLevel($role instanceof Role ? $role : null) < $actorLevel;
    }

    protected function isRoleInTenantScope(?Role $role, ?int $tenantId): bool
    {
        if (! $role) {
            return false;
        }

        $roleTenantId = $role->tenant_id !== null ? (int) $role->tenant_id : null;

        return $roleTenantId === $tenantId;
    }
}
