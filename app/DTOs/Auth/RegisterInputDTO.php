<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class RegisterInputDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            name: (string) ($input['name'] ?? ''),
            email: (string) ($input['email'] ?? ''),
            password: (string) ($input['password'] ?? '')
        );
    }
}
