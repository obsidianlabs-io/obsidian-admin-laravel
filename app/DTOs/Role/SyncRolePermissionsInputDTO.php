<?php

declare(strict_types=1);

namespace App\DTOs\Role;

final readonly class SyncRolePermissionsInputDTO
{
    /**
     * @param  list<string>  $permissionCodes
     */
    public function __construct(
        public array $permissionCodes
    ) {}
}
