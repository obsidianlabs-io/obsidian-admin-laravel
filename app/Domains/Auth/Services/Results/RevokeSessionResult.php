<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class RevokeSessionResult
{
    public function __construct(
        private int $deletedTokenCount,
        private bool $revokedCurrentSession
    ) {}

    public function deletedTokenCount(): int
    {
        return $this->deletedTokenCount;
    }

    public function revokedCurrentSession(): bool
    {
        return $this->revokedCurrentSession;
    }

    /**
     * @return array{deletedTokenCount: int, revokedCurrentSession: bool}
     */
    public function toArray(): array
    {
        return [
            'deletedTokenCount' => $this->deletedTokenCount,
            'revokedCurrentSession' => $this->revokedCurrentSession,
        ];
    }
}
