<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Models\Organization;
use App\DTOs\Organization\ListOrganizationsInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListOrganizationsQueryAction
{
    /**
     * @return Builder<Organization>
     */
    public function handle(int $tenantId, ListOrganizationsInputDTO $input): Builder
    {
        $query = Organization::query()
            ->with('tenant:id,name')
            ->withCount(['teams', 'users'])
            ->select(['id', 'tenant_id', 'code', 'name', 'description', 'status', 'sort', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $input);

        return $query;
    }

    /**
     * @param  Builder<Organization>  $query
     */
    private function applyFilters(Builder $query, ListOrganizationsInputDTO $input): void
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
    }
}
