<?php

declare(strict_types=1);

namespace App\DTOs\Tenant;

readonly class UpdateTenantDTO
{
    public function __construct(
        public string $tenantCode,
        public string $tenantName,
        public ?string $status,
    ) {}
}
