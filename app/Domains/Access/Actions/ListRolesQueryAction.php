<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\DTOs\Role\ListRolesInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListRolesQueryAction
{
    public function __construct(private readonly RoleScopeGuardService $roleScopeGuardService) {}

    /**
     * @return Builder<Role>
     */
    public function handle(
        ListRolesInputDTO $input,
        int $actorLevel,
        ?int $tenantId,
        bool $isSuper,
    ): Builder {
        $query = Role::query()
            ->with('tenant:id,name')
            ->with('permissions:id,code,status')
            ->withCount('users')
            ->select(['id', 'code', 'name', 'description', 'status', 'tenant_id', 'level', 'created_at', 'updated_at'])
            ->upToLevel($actorLevel);

        $this->roleScopeGuardService->applyRoleVisibilityScope($query, $tenantId, $isSuper);
        $this->applyFilters($query, $input);

        return $query;
    }

    /**
     * @param  Builder<Role>  $query
     */
    private function applyFilters(Builder $query, ListRolesInputDTO $input): void
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

        if ($input->level !== null && $input->level > 0) {
            $query->where('level', $input->level);
        }
    }
}
