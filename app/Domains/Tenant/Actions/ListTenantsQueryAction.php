<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Actions;

use App\Domains\Tenant\Models\Tenant;
use App\DTOs\Tenant\ListTenantsInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListTenantsQueryAction
{
    /**
     * @return Builder<Tenant>
     */
    public function handle(ListTenantsInputDTO $input): Builder
    {
        $query = Tenant::query()
            ->withCount('users')
            ->select(['id', 'code', 'name', 'status', 'created_at', 'updated_at']);

        $this->applyFilters($query, $input);

        return $query;
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyFilters(Builder $query, ListTenantsInputDTO $input): void
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
