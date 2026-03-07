<?php

declare(strict_types=1);

namespace App\DTOs\Role;

readonly class SyncRolePermissionsDTO
{
    /**
     * @param  list<int>  $permissionIds
     */
    public function __construct(
        public array $permissionIds,
    ) {}
}
