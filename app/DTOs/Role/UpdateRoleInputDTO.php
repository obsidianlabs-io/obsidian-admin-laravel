<?php

declare(strict_types=1);

namespace App\DTOs\Role;

final readonly class UpdateRoleInputDTO
{
    /**
     * @param  list<string>  $permissionCodes
     */
    public function __construct(
        public string $roleCode,
        public string $roleName,
        public string $description,
        public ?string $status,
        public int $level,
        public bool $hasPermissionCodes,
        public array $permissionCodes
    ) {}

    public function toUpdateRoleDTO(string $fallbackStatus): UpdateRoleDTO
    {
        return new UpdateRoleDTO(
            code: $this->roleCode,
            name: $this->roleName,
            description: $this->description,
            status: $this->status ?? $fallbackStatus,
            level: $this->level,
        );
    }
}
