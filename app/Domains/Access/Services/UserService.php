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

    public function assignRole(User $user, Role $role): User
    {
        $user->forceFill(['role_id' => $role->id])->save();
        $this->apiCacheService->bump('users');

        return $user;
    }
}
