<?php

declare(strict_types=1);

namespace App\DTOs\Role;

readonly class UpdateRoleDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $status,
        public int $level,
    ) {}
}
