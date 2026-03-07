<?php

declare(strict_types=1);

namespace App\DTOs\Permission;

readonly class CreatePermissionDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $group,
        public string $description,
        public string $status,
    ) {}
}
