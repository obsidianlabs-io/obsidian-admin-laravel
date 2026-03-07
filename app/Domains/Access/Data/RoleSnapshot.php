<?php

declare(strict_types=1);

namespace App\Domains\Access\Data;

use App\Domains\Access\Models\Role;

final readonly class RoleSnapshot
{
    public function __construct(
        public string $roleCode,
        public string $roleName,
        public ?string $description = null,
        public ?string $status = null,
        public ?int $level = null,
        public ?int $tenantId = null,
        public ?int $permissionCount = null,
        public bool $includeTenantId = false,
    ) {}

    public static function forCreateAudit(Role $role, ?int $tenantId, int $permissionCount): self
    {
        return new self(
            roleCode: (string) $role->code,
            roleName: (string) $role->name,
            tenantId: $tenantId,
            level: (int) ($role->level ?? 0),
            permissionCount: $permissionCount,
            includeTenantId: true,
        );
    }

    public static function forUpdateAudit(Role $role): self
    {
        return new self(
            roleCode: (string) $role->code,
            roleName: (string) $role->name,
            description: (string) ($role->description ?? ''),
            status: (string) $role->status,
            level: (int) ($role->level ?? 0),
        );
    }

    public static function forStatusAudit(Role $role): self
    {
        return new self(
            roleCode: (string) $role->code,
            roleName: (string) $role->name,
            tenantId: $role->tenant_id !== null ? (int) $role->tenant_id : null,
            status: (string) $role->status,
            includeTenantId: true,
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        $payload = [
            'roleCode' => $this->roleCode,
            'roleName' => $this->roleName,
        ];

        if ($this->description !== null) {
            $payload['description'] = $this->description;
        }

        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }

        if ($this->includeTenantId) {
            $payload['tenantId'] = $this->tenantId;
        }

        if ($this->level !== null) {
            $payload['level'] = $this->level;
        }

        if ($this->permissionCount !== null) {
            $payload['permissionCount'] = $this->permissionCount;
        }

        return $payload;
    }
}
