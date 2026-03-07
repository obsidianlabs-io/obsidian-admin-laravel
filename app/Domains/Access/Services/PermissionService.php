<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Permission;
use App\Domains\Shared\Services\ApiCacheService;
use App\DTOs\Permission\CreatePermissionDTO;
use App\DTOs\Permission\UpdatePermissionDTO;

class PermissionService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(CreatePermissionDTO $dto): Permission
    {
        $permission = Permission::query()->create([
            'code' => $dto->code,
            'name' => $dto->name,
            'group' => $dto->group,
            'description' => $dto->description,
            'status' => $dto->status,
        ]);

        $this->apiCacheService->bump('permissions');

        return $permission;
    }

    public function update(Permission $permission, UpdatePermissionDTO $dto): Permission
    {
        $permission->forceFill([
            'name' => $dto->name,
            'group' => $dto->group,
            'description' => $dto->description,
            'status' => $dto->status,
        ])->save();
        $this->apiCacheService->bump('permissions');

        return $permission;
    }

    public function delete(Permission $permission): void
    {
        $permission->delete();
        $this->apiCacheService->bump('permissions');
    }
}
