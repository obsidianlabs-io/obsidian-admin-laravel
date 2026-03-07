<?php

declare(strict_types=1);

namespace App\Domains\Access\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Access\Services\UserTenantScopeService;
use App\DTOs\User\ListUsersInputDTO;
use Illuminate\Database\Eloquent\Builder;

final class ListUsersQueryAction
{
    public function __construct(private readonly UserTenantScopeService $userTenantScopeService) {}

    /**
     * @return Builder<User>
     */
    public function handle(
        User $authUser,
        int $actorLevel,
        ?int $tenantId,
        bool $isSuper,
        ListUsersInputDTO $input,
    ): Builder {
        $query = $this->userTenantScopeService->buildListQuery($authUser, $actorLevel, $tenantId, $isSuper);
        $this->userTenantScopeService->applyListFilters(
            $query,
            $input->keyword,
            $input->userName,
            $input->userEmail,
            $input->roleCode,
            $input->status,
        );

        return $query;
    }
}
