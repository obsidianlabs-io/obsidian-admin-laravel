<?php

declare(strict_types=1);

namespace App\DTOs\User;

readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $status,
        public int $roleId,
        public ?int $tenantId
    ) {}
}
