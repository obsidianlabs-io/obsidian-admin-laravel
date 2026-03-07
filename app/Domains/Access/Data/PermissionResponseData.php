<?php

declare(strict_types=1);

namespace App\Domains\Access\Data;

use App\Domains\Access\Models\Permission;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final readonly class PermissionResponseData
{
    public function __construct(
        public int $id,
        public string $permissionCode,
        public string $permissionName,
        public ?string $version = null,
        public ?string $updateTime = null,
    ) {}

    public static function fromModel(Permission $permission, ?Request $request = null): self
    {
        return new self(
            id: (int) $permission->id,
            permissionCode: (string) $permission->code,
            permissionName: (string) $permission->name,
            version: $request instanceof Request
                ? (string) ($permission->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
                : null,
            updateTime: $request instanceof Request
                ? ApiDateTime::formatForRequest($permission->updated_at, $request)
                : null,
        );
    }

    /**
     * @return array{id: int, permissionCode: string, permissionName: string, version?: string, updateTime?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'permissionCode' => $this->permissionCode,
            'permissionName' => $this->permissionName,
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
