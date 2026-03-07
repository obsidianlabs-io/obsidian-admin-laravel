<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class AssignUserRoleInputDTO
{
    public function __construct(
        public string $roleCode
    ) {}
}
