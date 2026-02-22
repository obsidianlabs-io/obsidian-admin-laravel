<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Contracts;

interface ActiveTenantResolver
{
    public function findActiveTenantIdByCode(string $tenantCode): ?int;
}
