<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

use LogicException;

final readonly class RevokeSessionActionResult
{
    private function __construct(
        private bool $ok,
        private ?string $code,
        private ?string $message,
        private ?string $sessionId,
        private int $deletedTokenCount,
        private bool $revokedCurrentSession,
    ) {}

    public static function success(
        string $sessionId,
        int $deletedTokenCount,
        bool $revokedCurrentSession,
    ): self {
        return new self(
            ok: true,
            code: null,
            message: null,
            sessionId: $sessionId,
            deletedTokenCount: $deletedTokenCount,
            revokedCurrentSession: $revokedCurrentSession,
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code,
            message: $message,
            sessionId: null,
            deletedTokenCount: 0,
            revokedCurrentSession: false,
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
        return $this->message ?? 'Session revoked';
    }

    /**
     * @return array{sessionId: string, deletedTokenCount: int, revokedCurrentSession: bool}
     */
    public function payload(): array
    {
        if ($this->sessionId === null) {
            throw new LogicException('Revoke session result does not contain payload.');
        }

        return [
            'sessionId' => $this->sessionId,
            'deletedTokenCount' => $this->deletedTokenCount,
            'revokedCurrentSession' => $this->revokedCurrentSession,
        ];
    }

    public function revokedCurrentSession(): bool
    {
        return $this->revokedCurrentSession;
    }
}
