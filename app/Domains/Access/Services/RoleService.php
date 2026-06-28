<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Data\RoleSnapshot;
use App\Domains\Access\Models\Role;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\DTOs\Role\CreateRoleDTO;
use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    /**
     * @param  list<int>  $permissionIds
     */
    public function create(CreateRoleDTO $dto, array $permissionIds = [], ?AuditContext $audit = null): Role
    {
        $role = DB::transaction(function () use ($dto, $permissionIds): Role {
            $role = Role::query()->create([
                'code' => $dto->code,
                'name' => $dto->name,
                'description' => $dto->description,
                'status' => $dto->status,
                'tenant_id' => $dto->tenantId,
                'level' => $dto->level,
            ]);

            $role->permissions()->sync($permissionIds);

            return $role;
        });

        $this->apiCacheService->bump('roles');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $role, $permissionIds) {
                $effectiveTenantId = $audit->tenantId ?? ($role->tenant_id !== null ? (int) $role->tenant_id : null);
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'role.create',
                    auditable: $role,
                    actor: $audit->actor,
                    newValues: RoleSnapshot::forCreateAudit($role, $effectiveTenantId, count($permissionIds))->toArray(),
                    tenantId: $effectiveTenantId,
                ));
            });
        }

        return $role;
    }

    /**
     * @param  list<int>|null  $permissionIds
     */
    public function update(Role $role, UpdateRoleDTO $dto, ?array $permissionIds = null, ?AuditContext $audit = null): Role
    {
        DB::transaction(function () use ($role, $dto, $permissionIds): void {
            $role->forceFill([
                'code' => $dto->code,
                'name' => $dto->name,
                'description' => $dto->description,
                'status' => $dto->status,
                'level' => $dto->level,
            ])->save();

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }
        });
        $this->apiCacheService->bump('roles');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $role) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'role.update',
                    auditable: $role,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: RoleSnapshot::forUpdateAudit($role)->toArray(),
                    tenantId: $audit->tenantId ?? ($role->tenant_id !== null ? (int) $role->tenant_id : null),
                ));
            });
        }

        return $role;
    }

    public function syncPermissions(Role $role, SyncRolePermissionsDTO $dto, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($role, $dto): void {
            $role->touch();
            $role->permissions()->sync($dto->permissionIds);
        });
        $this->apiCacheService->bump('roles');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $role) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'role.sync_permissions',
                    auditable: $role,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: $audit->newValues,
                    tenantId: $audit->tenantId ?? ($role->tenant_id ? (int) $role->tenant_id : null),
                ));
            });
        }
    }

    public function delete(Role $role, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });
        $this->apiCacheService->bump('roles');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $role) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'role.soft_delete',
                    auditable: $role,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    tenantId: $audit->tenantId ?? ($role->tenant_id !== null ? (int) $role->tenant_id : null)
                ));
            });
        }
    }
}
