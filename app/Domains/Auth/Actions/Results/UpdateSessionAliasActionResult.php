<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Results;

use LogicException;

final readonly class UpdateSessionAliasActionResult
{
    private function __construct(
        private bool $ok,
        private ?string $code,
        private ?string $message,
        private ?string $sessionId,
        private ?string $deviceAlias,
        private int $updatedTokenCount,
        private bool $updatedCurrentSession,
    ) {}

    public static function success(
        string $sessionId,
        ?string $deviceAlias,
        int $updatedTokenCount,
        bool $updatedCurrentSession,
    ): self {
        return new self(
            ok: true,
            code: null,
            message: null,
            sessionId: $sessionId,
            deviceAlias: $deviceAlias,
            updatedTokenCount: $updatedTokenCount,
            updatedCurrentSession: $updatedCurrentSession,
        );
    }

    public static function failure(string $code, string $message): self
    {
        return new self(
            ok: false,
            code: $code,
            message: $message,
            sessionId: null,
            deviceAlias: null,
            updatedTokenCount: 0,
            updatedCurrentSession: false,
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
        return $this->message ?? 'Session alias updated';
    }

    /**
     * @return array{sessionId: string, deviceAlias: string, updatedTokenCount: int, updatedCurrentSession: bool}
     */
    public function payload(): array
    {
        if ($this->sessionId === null) {
            throw new LogicException('Update session alias result does not contain payload.');
        }

        return [
            'sessionId' => $this->sessionId,
            'deviceAlias' => (string) ($this->deviceAlias ?? ''),
            'updatedTokenCount' => $this->updatedTokenCount,
            'updatedCurrentSession' => $this->updatedCurrentSession,
        ];
    }

    public function updatedCurrentSession(): bool
    {
        return $this->updatedCurrentSession;
    }
}
