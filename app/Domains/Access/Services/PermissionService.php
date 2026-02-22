<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Permission;
use App\Domains\Shared\Services\ApiCacheService;

class PermissionService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    /**
     * @param  array{code: string, name: string, group?: string, description?: string, status?: string}  $payload
     */
    public function create(array $payload): Permission
    {
        $permission = Permission::query()->create([
            'code' => $payload['code'],
            'name' => $payload['name'],
            'group' => (string) ($payload['group'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'status' => (string) ($payload['status'] ?? '1'),
        ]);

        $this->apiCacheService->bump('permissions');

        return $permission;
    }

    /**
     * @param  array{code: string, name: string, group?: string, description?: string, status?: string}  $payload
     */
    public function update(Permission $permission, array $payload): Permission
    {
        $permission->forceFill([
            'name' => $payload['name'],
            'group' => (string) ($payload['group'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'status' => (string) ($payload['status'] ?? $permission->status),
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
