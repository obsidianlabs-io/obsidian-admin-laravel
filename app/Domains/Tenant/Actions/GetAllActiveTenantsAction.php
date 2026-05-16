<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Models\Tenant;

final class GetAllActiveTenantsAction
{
    /**
     * @return list<array{id: int, tenantCode: string, tenantName: string}>
     */
    public function __invoke(): array
    {
        return array_values(
            Tenant::query()
                ->where('status', '1')
                ->orderBy('id')
                ->get(['id', 'code', 'name'])
                ->map(static function (Tenant $tenant): array {
                    return [
                        'id' => $tenant->id,
                        'tenantCode' => $tenant->code,
                        'tenantName' => $tenant->name,
                    ];
                })
                ->all()
        );
    }
}
