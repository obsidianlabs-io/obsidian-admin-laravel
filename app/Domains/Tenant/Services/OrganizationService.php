<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Services;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Data\OrganizationSnapshot;
use App\Domains\Tenant\Models\Organization;
use App\DTOs\Organization\CreateOrganizationDTO;
use App\DTOs\Organization\UpdateOrganizationDTO;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(int $tenantId, CreateOrganizationDTO $dto, ?AuditContext $audit = null): Organization
    {
        $organization = DB::transaction(function () use ($tenantId, $dto): Organization {
            return Organization::query()->create([
                'tenant_id' => $tenantId,
                'code' => $dto->organizationCode,
                'name' => $dto->organizationName,
                'description' => $dto->description,
                'status' => $dto->status,
                'sort' => $dto->sort,
            ]);
        });

        $this->apiCacheService->bump('organizations');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $organization, $tenantId) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'organization.create',
                    auditable: $organization,
                    actor: $audit->actor,
                    newValues: OrganizationSnapshot::fromModel($organization)->toArray(),
                    tenantId: $tenantId,
                ));
            });
        }

        return $organization;
    }

    public function update(Organization $organization, UpdateOrganizationDTO $dto, ?AuditContext $audit = null): Organization
    {
        $updated = DB::transaction(function () use ($organization, $dto): Organization {
            $organization->forceFill([
                'code' => $dto->organizationCode,
                'name' => $dto->organizationName,
                'description' => $dto->description,
                'status' => $dto->status ?? (string) $organization->status,
                'sort' => $dto->sort ?? (int) ($organization->sort ?? 0),
            ])->save();

            return $organization;
        });

        $this->apiCacheService->bump('organizations');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'organization.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: OrganizationSnapshot::fromModel($updated)->toArray(),
                    tenantId: $updated->tenant_id ? (int) $updated->tenant_id : null,
                ));
            });
        }

        return $updated;
    }

    public function delete(Organization $organization, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($organization): void {
            $organization->delete();
        });
        $this->apiCacheService->bump('organizations');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $organization) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'organization.soft_delete',
                    auditable: $organization,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    tenantId: $organization->tenant_id ? (int) $organization->tenant_id : null,
                ));
            });
        }
    }
}
