<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

final readonly class LoginInputDTO
{
    public function __construct(
        public ?string $userName,
        public ?string $email,
        public string $password,
        public bool $rememberMe,
        public ?string $otpCode,
        public ?string $locale
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input): self
    {
        $userName = isset($input['userName']) ? trim((string) $input['userName']) : '';
        $email = isset($input['email']) ? trim((string) $input['email']) : '';
        $otpCode = isset($input['otpCode']) ? trim((string) $input['otpCode']) : '';
        $locale = isset($input['locale']) ? trim((string) $input['locale']) : '';

        return new self(
            userName: $userName !== '' ? $userName : null,
            email: $email !== '' ? $email : null,
            password: (string) $input['password'],
            rememberMe: filter_var($input['rememberMe'] ?? false, FILTER_VALIDATE_BOOLEAN),
            otpCode: $otpCode !== '' ? $otpCode : null,
            locale: $locale !== '' ? $locale : null
        );
    }

    public function loginKey(): string
    {
        return $this->userName ?? $this->email ?? '';
    }
}
