<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

use App\Domains\Auth\Services\Results\TokenPairResult;
use LogicException;

final readonly class RefreshTokenResult
{
    private function __construct(
        private bool $ok,
        private ?TokenPairResult $tokens,
        private ?string $code,
        private ?string $message,
    ) {}

    public static function success(TokenPairResult $tokens): self
    {
        return new self(
            ok: true,
            tokens: $tokens,
            code: null,
            message: null,
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            tokens: null,
            code: $code,
            message: $message,
        );
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function code(): string
    {
        return $this->code ?? '0000';
    }

    public function message(): string
    {
        return $this->message ?? '';
    }

    public function requireTokens(): TokenPairResult
    {
        if (! $this->tokens instanceof TokenPairResult) {
            throw new LogicException('Refresh token result does not contain tokens.');
        }

        return $this->tokens;
    }
}
