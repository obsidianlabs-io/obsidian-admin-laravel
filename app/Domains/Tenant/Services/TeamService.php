<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Services;

use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Data\TeamSnapshot;
use App\Domains\Tenant\Models\Team;
use App\DTOs\Team\CreateTeamDTO;
use App\DTOs\Team\UpdateTeamDTO;
use Illuminate\Support\Facades\DB;

class TeamService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    public function create(int $tenantId, CreateTeamDTO $dto, ?AuditContext $audit = null): Team
    {
        $team = DB::transaction(function () use ($tenantId, $dto): Team {
            return Team::query()->create([
                'tenant_id' => $tenantId,
                'organization_id' => $dto->organizationId,
                'code' => $dto->teamCode,
                'name' => $dto->teamName,
                'description' => $dto->description,
                'status' => $dto->status,
                'sort' => $dto->sort,
            ]);
        });

        $this->apiCacheService->bump('teams');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $team, $tenantId) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'team.create',
                    auditable: $team,
                    actor: $audit->actor,
                    newValues: TeamSnapshot::fromModel($team)->toArray(),
                    tenantId: $tenantId,
                ));
            });
        }

        return $team;
    }

    public function update(Team $team, UpdateTeamDTO $dto, ?AuditContext $audit = null): Team
    {
        $updated = DB::transaction(function () use ($team, $dto): Team {
            $team->forceFill([
                'organization_id' => $dto->organizationId,
                'code' => $dto->teamCode,
                'name' => $dto->teamName,
                'description' => $dto->description,
                'status' => $dto->status ?? (string) $team->status,
                'sort' => $dto->sort ?? (int) ($team->sort ?? 0),
            ])->save();

            return $team;
        });

        $this->apiCacheService->bump('teams');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $updated) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'team.update',
                    auditable: $updated,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    newValues: TeamSnapshot::fromModel($updated)->toArray(),
                    tenantId: $updated->tenant_id ? (int) $updated->tenant_id : null,
                ));
            });
        }

        return $updated;
    }

    public function delete(Team $team, ?AuditContext $audit = null): void
    {
        DB::transaction(function () use ($team): void {
            $team->delete();
        });
        $this->apiCacheService->bump('teams');

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $team) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'team.soft_delete',
                    auditable: $team,
                    actor: $audit->actor,
                    oldValues: $audit->oldValues,
                    tenantId: $team->tenant_id ? (int) $team->tenant_id : null,
                ));
            });
        }
    }
}
