<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Data\TenantSnapshot;
use App\Domains\Tenant\Models\Tenant;
use App\DTOs\Tenant\CreateTenantDTO;
use Illuminate\Support\Facades\DB;

readonly class CreateTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(CreateTenantDTO $dto, ?AuditContext $audit = null): Tenant
    {
        $tenant = DB::transaction(function () use ($dto): Tenant {
            return Tenant::query()->create([
                'code' => trim($dto->tenantCode),
                'name' => trim($dto->tenantName),
                'status' => $dto->status,
            ]);
        });

        $this->apiCacheService->bump('tenants');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $tenant) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'tenant.create',
                    auditable: $tenant,
                    actor: $audit->actor,
                    newValues: TenantSnapshot::fromModel($tenant)->toArray(),
                ));
            });
        }

        return $tenant;
    }
}
