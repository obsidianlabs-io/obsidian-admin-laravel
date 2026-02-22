<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Shared\Services\ApiCacheService;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    /**
     * @param  array{code: string, name: string, description?: string, status?: string, tenant_id?: int|null, level?: int}  $payload
     * @param  list<int>  $permissionIds
     */
    public function create(array $payload, array $permissionIds = []): Role
    {
        $role = DB::transaction(function () use ($payload, $permissionIds): Role {
            $role = Role::query()->create([
                'code' => $payload['code'],
                'name' => $payload['name'],
                'description' => (string) ($payload['description'] ?? ''),
                'status' => (string) ($payload['status'] ?? '1'),
                'tenant_id' => $payload['tenant_id'] ?? null,
                'level' => (int) ($payload['level'] ?? 10),
            ]);

            $role->permissions()->sync($permissionIds);

            return $role;
        });

        $this->apiCacheService->bump('roles');

        return $role;
    }

    /**
     * @param  array{code: string, name: string, description?: string, status?: string, level?: int}  $payload
     * @param  list<int>|null  $permissionIds
     */
    public function update(Role $role, array $payload, ?array $permissionIds = null): Role
    {
        DB::transaction(function () use ($role, $payload, $permissionIds): void {
            $role->forceFill([
                'code' => $payload['code'],
                'name' => $payload['name'],
                'description' => (string) ($payload['description'] ?? ''),
                'status' => (string) ($payload['status'] ?? $role->status),
                'level' => (int) ($payload['level'] ?? $role->level ?? 10),
            ])->save();

            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }
        });
        $this->apiCacheService->bump('roles');

        return $role;
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        DB::transaction(function () use ($role, $permissionIds): void {
            $role->permissions()->sync($permissionIds);
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
