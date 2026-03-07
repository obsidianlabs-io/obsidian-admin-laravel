<?php

declare(strict_types=1);

namespace App\DTOs\User;

readonly class UpdateOwnProfileDTO
{
    public function __construct(
        public string $userName,
        public string $email,
        public ?string $password = null,
        public ?string $timezone = null,
    ) {}
}
