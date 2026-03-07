<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class ForgotPasswordInputDTO
{
    public function __construct(
        public string $email
    ) {}
}
