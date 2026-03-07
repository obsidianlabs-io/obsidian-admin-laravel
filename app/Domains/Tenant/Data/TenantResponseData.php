<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Tenant;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final readonly class TenantResponseData
{
    public function __construct(
        public int $id,
        public TenantSnapshot $snapshot,
        public ?string $version = null,
        public ?string $updateTime = null,
    ) {}

    public static function fromModel(Tenant $tenant, ?Request $request = null): self
    {
        return new self(
            id: (int) $tenant->id,
            snapshot: TenantSnapshot::fromModel($tenant),
            version: $request instanceof Request
                ? (string) ($tenant->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
                : null,
            updateTime: $request instanceof Request
                ? ApiDateTime::formatForRequest($tenant->updated_at, $request)
                : null,
        );
    }

    /**
     * @return array{id: int, tenantCode: string, tenantName: string, status: string, version?: string, updateTime?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            ...$this->snapshot->toArray(),
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
