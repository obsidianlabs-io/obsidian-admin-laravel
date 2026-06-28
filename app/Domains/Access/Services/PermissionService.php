<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Data\PermissionSnapshot;
use App\Domains\Access\Models\Permission;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\DTOs\Permission\CreatePermissionDTO;
use App\DTOs\Permission\UpdatePermissionDTO;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(CreatePermissionDTO $dto, ?AuditContext $audit = null): Permission
    {
        $permission = DB::transaction(function () use ($dto): Permission {
            return Permission::query()->create([
                'code' => $dto->code,
                'name' => $dto->name,
                'group' => $dto->group,
                'description' => $dto->description,
                'status' => $dto->status,
            ]);
        });

        $this->apiCacheService->bump('permissions');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $permission) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'permission.create',
                    auditable: $permission,
                    actor: $audit->actor,
                    newValues: PermissionSnapshot::fromModel($permission)->toArray(),
                    tenantId: $audit->tenantId
                ));
            });
        }

        return $permission;
    }

    public function update(Permission $permission, UpdatePermissionDTO $dto, ?AuditContext $audit = null): Permission
    {
        $updated = DB::transaction(function () use ($permission, $dto): Permission {
            $permission->forceFill([
                'name' => $dto->name,
                'group' => $dto->group,
                'description' => $dto->description,
                'status' => $dto->status,
            ])->save();

            return $permission;
        });

        $this->apiCacheService->bump('permissions');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'permission.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: PermissionSnapshot::fromModel($updated)->toArray(),
                    tenantId: $audit->tenantId
                ));
            });
        }

        return $updated;
    }

    public function delete(Permission $permission, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($permission): void {
            $permission->delete();
        });

        $this->apiCacheService->bump('permissions');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $permission) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'permission.soft_delete',
                    auditable: $permission,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    tenantId: $audit->tenantId
                ));
            });
        }
    }
}
