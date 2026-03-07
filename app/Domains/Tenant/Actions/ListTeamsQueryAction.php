<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Models\Team;
use App\DTOs\Team\ListTeamsInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListTeamsQueryAction
{
    /**
     * @return Builder<Team>
     */
    public function handle(int $tenantId, ListTeamsInputDTO $input): Builder
    {
        $query = Team::query()
            ->with('organization:id,name')
            ->withCount('users')
            ->select(['id', 'tenant_id', 'organization_id', 'code', 'name', 'description', 'status', 'sort', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $input);

        return $query;
    }

    /**
     * @param  Builder<Team>  $query
     */
    private function applyFilters(Builder $query, ListTeamsInputDTO $input): void
    {
        if ($input->keyword !== '') {
            $query->where(function (Builder $builder) use ($input): void {
                $builder->where('code', 'like', '%'.$input->keyword.'%')
                    ->orWhere('name', 'like', '%'.$input->keyword.'%');
            });
        }

        if ($input->status !== '') {
            $query->where('status', $input->status);
        }

        if ($input->organizationId !== null && $input->organizationId > 0) {
            $query->where('organization_id', $input->organizationId);
        }
    }
}
