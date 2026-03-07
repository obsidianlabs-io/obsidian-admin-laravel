<?php

declare(strict_types=1);

namespace App\DTOs\Role;

final readonly class StoreRoleInputDTO
{
    /**
     * @param  list<string>  $permissionCodes
     */
    public function __construct(
        public string $roleCode,
        public string $roleName,
        public string $description,
        public string $status,
        public int $level,
        public array $permissionCodes
    ) {}

    public function toCreateRoleDTO(?int $tenantId): CreateRoleDTO
    {
        return new CreateRoleDTO(
            code: $this->roleCode,
            name: $this->roleName,
            description: $this->description,
            status: $this->status,
            tenantId: $tenantId,
            level: $this->level,
        );
    }
}
