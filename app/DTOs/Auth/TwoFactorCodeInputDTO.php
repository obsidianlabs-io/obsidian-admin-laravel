<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class TwoFactorCodeInputDTO
{
    public function __construct(
        public string $otpCode
    ) {}
}
