<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Data\TenantSnapshot;
use App\Domains\Tenant\Models\Tenant;
use App\DTOs\Tenant\UpdateTenantDTO;
use Illuminate\Support\Facades\DB;

readonly class UpdateTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(Tenant $tenant, UpdateTenantDTO $dto, ?AuditContext $audit = null): Tenant
    {
        $updated = DB::transaction(function () use ($tenant, $dto): Tenant {
            $tenant->forceFill([
                'code' => trim($dto->tenantCode),
                'name' => trim($dto->tenantName),
                'status' => $dto->status ?? (string) $tenant->status,
            ])->save();

            return $tenant;
        });

        $this->apiCacheService->bump('tenants');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'tenant.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: TenantSnapshot::fromModel($updated)->toArray(),
                ));
            });
        }

        return $updated;
    }
}
