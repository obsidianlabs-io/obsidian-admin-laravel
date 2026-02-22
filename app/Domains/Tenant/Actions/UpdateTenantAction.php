<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Models\Tenant;
use App\DTOs\Tenant\UpdateTenantDTO;

readonly class UpdateTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(Tenant $tenant, UpdateTenantDTO $dto): Tenant
    {
        $tenant->forceFill([
            'code' => trim($dto->tenantCode),
            'name' => trim($dto->tenantName),
            'status' => $dto->status ?? (string) $tenant->status,
        ])->save();

        $this->apiCacheService->bump('tenants');

        return $tenant;
    }
}
