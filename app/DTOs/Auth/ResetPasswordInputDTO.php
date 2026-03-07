<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class ResetPasswordInputDTO
{
    public function __construct(
        public string $token,
        public string $email,
        public string $password,
        public string $passwordConfirmation
    ) {}

    /**
     * @return array{token: string, email: string, password: string, password_confirmation: string}
     */
    public function toBrokerPayload(): array
    {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
