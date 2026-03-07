<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class TokenPairResult
{
    public function __construct(
        private string $token,
        private string $refreshToken
    ) {}

    public function token(): string
    {
        return $this->token;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * @return array{token: string, refreshToken: string}
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'refreshToken' => $this->refreshToken,
        ];
    }
}
