<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\DTOs\User\UpdateOwnProfileDTO;

final readonly class UpdateProfileInputDTO
{
    public function __construct(
        public string $userName,
        public string $email,
        public ?string $currentPassword,
        public ?string $password,
        public ?string $timezone
    ) {}

    public function toUpdateOwnProfileDTO(): UpdateOwnProfileDTO
    {
        return new UpdateOwnProfileDTO(
            userName: $this->userName,
            email: $this->email,
            password: $this->password,
            timezone: $this->timezone,
        );
    }
}
