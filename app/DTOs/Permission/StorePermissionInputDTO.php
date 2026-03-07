<?php

declare(strict_types=1);

namespace App\DTOs\Permission;

final readonly class StorePermissionInputDTO
{
    public function __construct(
        public string $permissionCode,
        public string $permissionName,
        public string $group,
        public string $description,
        public string $status
    ) {}

    public function toCreatePermissionDTO(): CreatePermissionDTO
    {
        return new CreatePermissionDTO(
            code: $this->permissionCode,
            name: $this->permissionName,
            group: $this->group,
            description: $this->description,
            status: $this->status,
        );
    }
}
