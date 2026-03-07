<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class RefreshTokenInputDTO
{
    public function __construct(public string $refreshToken) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(refreshToken: (string) ($input['refreshToken'] ?? ''));
    }
}
