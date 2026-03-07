<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class UpdateUserInputDTO
{
    public function __construct(
        public string $userName,
        public string $email,
        public string $roleCode,
        public ?int $organizationId,
        public ?int $teamId,
        public ?string $status,
        public ?string $password
    ) {}

    public function toUpdateUserDTO(
        int $roleId,
        ?int $tenantId,
        ?int $organizationId,
        ?int $teamId,
        string $fallbackStatus
    ): UpdateUserDTO {
        return new UpdateUserDTO(
            name: $this->userName,
            email: $this->email,
            password: $this->password,
            status: $this->status ?? $fallbackStatus,
            roleId: $roleId,
            tenantId: $tenantId,
            organizationId: $organizationId,
            teamId: $teamId,
        );
    }
}
