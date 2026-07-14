<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class CreateUserInputDTO
{
    public function __construct(
        public string $userName,
        public string $email,
        public string $roleCode,
        public ?int $organizationId,
        public ?int $teamId,
        public string $status,
        public string $password
    ) {}

}
