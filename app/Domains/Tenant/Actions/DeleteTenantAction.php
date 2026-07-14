<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;

readonly class DeleteTenantAction
{
    public function __construct(
        private ApiCacheService $apiCacheService,
    ) {}

    public function __invoke(Tenant $tenant, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($tenant): void {
            $tenant->delete();
        });

        $this->apiCacheService->bump('tenants');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $tenant) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'tenant.soft_delete',
                    auditable: $tenant,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                ));
            });
        }
    }
}
