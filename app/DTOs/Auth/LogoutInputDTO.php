<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class LogoutInputDTO
{
    public function __construct(public ?string $refreshToken) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $refreshToken = trim((string) ($input['refreshToken'] ?? ''));

        return new self(refreshToken: $refreshToken !== '' ? $refreshToken : null);
    }
}
