<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Shared\Services\ApiCacheService;
use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;

class UserService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(CreateUserDTO $dto): User
    {
        $user = User::query()->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
            'status' => $dto->status,
            'role_id' => $dto->roleId,
            'tenant_id' => $dto->tenantId,
            'organization_id' => $dto->organizationId,
            'team_id' => $dto->teamId,
            'tenant_scope_id' => $dto->tenantId ?? 0,
        ]);

        $user->preference()->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);
        $this->apiCacheService->bump('users');

        return $user;
    }

    public function update(User $user, UpdateUserDTO $dto): User
    {
        $payload = [
            'name' => $dto->name,
            'email' => $dto->email,
            'status' => $dto->status,
            'role_id' => $dto->roleId,
            'tenant_id' => $dto->tenantId,
            'organization_id' => $dto->organizationId,
            'team_id' => $dto->teamId,
            'tenant_scope_id' => $dto->tenantId ?? 0,
        ];

        if ($dto->password !== null) {
            $payload['password'] = $dto->password;
        }

        $user->forceFill($payload)->save();
        $this->apiCacheService->bump('users');

        return $user;
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
        $this->apiCacheService->bump('users');
    }

    public function deactivate(User $user): User
    {
        $user->tokens()->delete();

        if ((string) $user->status !== '2') {
            $user->forceFill(['status' => '2'])->save();
        }

        $this->apiCacheService->bump('users');

        return $user;
    }

    public function assignRole(User $user, Role $role): User
    {
        $user->forceFill(['role_id' => $role->id])->save();
        $this->apiCacheService->bump('users');

        return $user;
    }

    /**
     * Resolve the role code for a user, preferring the eager-loaded relation
     * and falling back to a direct lookup when the relation is not loaded.
     */
    public function resolveRoleCode(User $user): string
    {
        $role = $user->getRelationValue('role');
        if ($role instanceof Role) {
            $attributes = $role->getAttributes();
            $code = $attributes['code'] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        $roleCode = $user->role_id
            ? Role::query()->whereKey((int) $user->role_id)->value('code')
            : null;

        return is_string($roleCode) ? $roleCode : '';
    }
}
