<?php

declare(strict_types=1);

namespace App\Domains\Access\Data;

use App\Domains\Access\Models\Permission;

final readonly class PermissionSnapshot
{
    public function __construct(
        public string $permissionCode,
        public string $permissionName,
        public string $status,
    ) {}

    public static function fromModel(Permission $permission): self
    {
        return new self(
            permissionCode: (string) $permission->code,
            permissionName: (string) $permission->name,
            status: (string) $permission->status,
        );
    }

    /**
     * @return array{permissionCode: string, permissionName: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'permissionCode' => $this->permissionCode,
            'permissionName' => $this->permissionName,
            'status' => $this->status,
        ];
    }
}
