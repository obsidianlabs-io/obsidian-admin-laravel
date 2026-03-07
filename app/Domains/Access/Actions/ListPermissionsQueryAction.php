<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Models\Permission;
use App\DTOs\Permission\ListPermissionsInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListPermissionsQueryAction
{
    /**
     * @return Builder<Permission>
     */
    public function handle(ListPermissionsInputDTO $input): Builder
    {
        $query = Permission::query()
            ->withCount('roles')
            ->select(['id', 'code', 'name', 'group', 'description', 'status', 'created_at', 'updated_at']);

        $this->applyFilters($query, $input);

        return $query;
    }

    /**
     * @param  Builder<Permission>  $query
     */
    private function applyFilters(Builder $query, ListPermissionsInputDTO $input): void
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

        if ($input->group !== '') {
            $query->where('group', $input->group);
        }
    }
}
