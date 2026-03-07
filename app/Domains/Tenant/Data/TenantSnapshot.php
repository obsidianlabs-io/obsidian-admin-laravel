<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Tenant;

final readonly class TenantSnapshot
{
    public function __construct(
        public string $tenantCode,
        public string $tenantName,
        public string $status,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self(
            tenantCode: (string) $tenant->code,
            tenantName: (string) $tenant->name,
            status: (string) $tenant->status,
        );
    }

    /**
     * @return array{tenantCode: string, tenantName: string, status: string}
     */
    public function toArray(): array
    {
        return [
            'tenantCode' => $this->tenantCode,
            'tenantName' => $this->tenantName,
            'status' => $this->status,
        ];
    }
}
