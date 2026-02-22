<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Contracts\ActiveTenantResolver;
use App\Domains\Tenant\Models\Tenant;

final class ResolveActiveTenantIdByCodeAction implements ActiveTenantResolver
{
    public function findActiveTenantIdByCode(string $tenantCode): ?int
    {
        $code = trim($tenantCode);
        if ($code === '') {
            return null;
        }

        $tenantId = Tenant::query()
            ->where('code', $code)
            ->where('status', '1')
            ->value('id');

        if (! is_numeric($tenantId)) {
            return null;
        }

        return (int) $tenantId;
    }
}
