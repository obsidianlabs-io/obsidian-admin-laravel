<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Role;
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
    public function create(CreateRoleDTO $dto, array $permissionIds = []): Role
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

        return $role;
    }

    /**
     * @param  list<int>|null  $permissionIds
     */
    public function update(Role $role, UpdateRoleDTO $dto, ?array $permissionIds = null): Role
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

        return $role;
    }

    public function syncPermissions(Role $role, SyncRolePermissionsDTO $dto): void
    {
        DB::transaction(function () use ($role, $dto): void {
            $role->permissions()->sync($dto->permissionIds);
            $role->touch();
        });
        $this->apiCacheService->bump('roles');
    }

    public function delete(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });
        $this->apiCacheService->bump('roles');
    }
}
