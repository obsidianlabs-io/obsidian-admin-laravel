<?php

declare(strict_types=1);

namespace App\DTOs\Role;

readonly class CreateRoleDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $status,
        public ?int $tenantId,
        public int $level,
    ) {}
}
