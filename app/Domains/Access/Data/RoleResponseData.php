<?php

declare(strict_types=1);

namespace App\Domains\Access\Data;

use App\Domains\Access\Models\Role;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final readonly class RoleResponseData
{
    public function __construct(
        public int $id,
        public string $roleCode,
        public string $roleName,
        public int $level,
        public ?string $version = null,
        public ?string $updateTime = null,
    ) {}

    public static function fromModel(Role $role, ?Request $request = null): self
    {
        return new self(
            id: (int) $role->id,
            roleCode: (string) $role->code,
            roleName: (string) $role->name,
            level: (int) ($role->level ?? 0),
            version: $request instanceof Request
                ? (string) ($role->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
                : null,
            updateTime: $request instanceof Request
                ? ApiDateTime::formatForRequest($role->updated_at, $request)
                : null,
        );
    }

    /**
     * @return array{id: int, roleCode: string, roleName: string, level: int, version?: string, updateTime?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'roleCode' => $this->roleCode,
            'roleName' => $this->roleName,
            'level' => $this->level,
        ];

        if ($this->version !== null) {
            $payload['version'] = $this->version;
        }

        if ($this->updateTime !== null) {
            $payload['updateTime'] = $this->updateTime;
        }

        return $payload;
    }
}
