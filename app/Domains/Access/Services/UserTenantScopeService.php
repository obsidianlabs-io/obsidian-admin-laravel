<?php

declare(strict_types=1);

namespace App\Domains\Access\Services;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\OrganizationTeamBindingResult;
use App\Domains\Shared\Support\TenantVisibility;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use Illuminate\Database\Eloquent\Builder;

final class UserTenantScopeService
{
    /**
     * @return Builder<User>
     */
    public function buildListQuery(User $authUser, int $actorLevel, ?int $tenantId, bool $isSuper): Builder
    {
        $query = User::query()
            ->with([
                'role:id,code,name,level',
                'organization:id,name',
                'team:id,name',
            ])
            ->select([
                'id',
                'name',
                'email',
                'status',
                'role_id',
                'tenant_id',
                'organization_id',
                'team_id',
                'created_at',
                'updated_at',
            ])
            ->where('id', '!=', $authUser->id)
            ->where(function (Builder $builder) use ($actorLevel): void {
                $builder->whereNull('role_id')
                    ->orWhereHas('role', function (Builder $roleQuery) use ($actorLevel): void {
                        $roleQuery->where('level', '<=', $actorLevel);
                    });
            });

        TenantVisibility::applyScope($query, $tenantId, $isSuper);

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function applyListFilters(
        Builder $query,
        string $keyword,
        string $userName,
        string $userEmail,
        string $roleCode,
        string $status
    ): void {
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%');
            });
        }

        if ($userName !== '') {
            $query->where('name', 'like', '%'.$userName.'%');
        }

        if ($userEmail !== '') {
            $query->where('email', 'like', '%'.$userEmail.'%');
        }

        if ($roleCode !== '') {
            $query->whereHas('role', function (Builder $builder) use ($roleCode): void {
                $builder->where('code', $roleCode);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }
    }

    public function findUserInTenantScope(int $id, ?int $tenantId, bool $isSuper): ?User
    {
        $query = User::query()->whereKey($id);
        TenantVisibility::applyScope($query, $tenantId, $isSuper);

        return $query->first();
    }

    public function resolveOrganizationTeamBinding(
        ?int $tenantId,
        mixed $organizationIdInput,
        mixed $teamIdInput
    ): OrganizationTeamBindingResult {
        $organizationId = is_numeric($organizationIdInput) ? (int) $organizationIdInput : null;
        $teamId = is_numeric($teamIdInput) ? (int) $teamIdInput : null;

        if ($organizationId !== null && $organizationId <= 0) {
            $organizationId = null;
        }
        if ($teamId !== null && $teamId <= 0) {
            $teamId = null;
        }

        if ($tenantId === null) {
            if ($organizationId !== null || $teamId !== null) {
                return OrganizationTeamBindingResult::failure(
                    '1002',
                    'Organization and team are not available for platform users'
                );
            }

            return OrganizationTeamBindingResult::success(null, null);
        }

        $organization = null;
        if ($organizationId !== null) {
            $organization = Organization::query()
                ->where('tenant_id', $tenantId)
                ->find($organizationId);

            if (! $organization) {
                return OrganizationTeamBindingResult::failure('1002', 'Organization not found');
            }

            if ((string) $organization->status !== '1') {
                return OrganizationTeamBindingResult::failure('1002', 'Organization is inactive');
            }
        }

        if ($teamId === null) {
            return OrganizationTeamBindingResult::success(
                $organization !== null ? (int) $organization->id : null,
                null
            );
        }

        $team = Team::query()
            ->where('tenant_id', $tenantId)
            ->find($teamId);

        if (! $team) {
            return OrganizationTeamBindingResult::failure('1002', 'Team not found');
        }

        if ((string) $team->status !== '1') {
            return OrganizationTeamBindingResult::failure('1002', 'Team is inactive');
        }

        if ($organization !== null && (int) $team->organization_id !== (int) $organization->id) {
            return OrganizationTeamBindingResult::failure('1002', 'Team does not belong to selected organization');
        }

        return OrganizationTeamBindingResult::success(
            $organization !== null ? (int) $organization->id : (int) $team->organization_id,
            (int) $team->id
        );
    }
}
