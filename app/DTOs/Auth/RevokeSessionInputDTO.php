<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class RevokeSessionInputDTO
{
    public function __construct(public string $sessionId) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(sessionId: (string) ($input['sessionId'] ?? ''));
    }
}
