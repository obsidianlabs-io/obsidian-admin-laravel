<?php

declare(strict_types=1);

namespace App\DTOs\Role;

final readonly class StoreRoleInputDTO extends CreateRoleDTO
{
    /**
     * @param  list<string>  $permissionCodes
     */
    public function __construct(
        public string $roleCode,
        public string $roleName,
        string $description,
        string $status,
        int $level,
        public array $permissionCodes,
        ?int $tenantId = null,
    ) {
        parent::__construct(
            code: $roleCode,
            name: $roleName,
            description: $description,
            status: $status,
            tenantId: $tenantId,
            level: $level,
        );
    }

    public function forTenant(?int $tenantId): self
    {
        return new self(
            roleCode: $this->roleCode,
            roleName: $this->roleName,
            description: $this->description,
            status: $this->status,
            level: $this->level,
            permissionCodes: $this->permissionCodes,
            tenantId: $tenantId,
        );
    }

    public function toCreateRoleDTO(?int $tenantId): CreateRoleDTO
    {
        return $this->forTenant($tenantId);
    }
}
