<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Models\Tenant;
use App\DTOs\Tenant\CreateTenantDTO;

readonly class CreateTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(CreateTenantDTO $dto): Tenant
    {
        $tenant = Tenant::query()->create([
            'code' => trim($dto->tenantCode),
            'name' => trim($dto->tenantName),
            'status' => $dto->status,
        ]);

        $this->apiCacheService->bump('tenants');

        return $tenant;
    }
}
