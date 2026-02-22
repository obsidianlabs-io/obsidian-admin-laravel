<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Models\Tenant;

readonly class DeleteTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(Tenant $tenant): void
    {
        $tenant->delete();
        $this->apiCacheService->bump('tenants');
    }
}
