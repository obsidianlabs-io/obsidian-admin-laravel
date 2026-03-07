<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

use Carbon\Carbon;

final readonly class SessionRecord
{
    public function __construct(
        public string $sessionId,
        public bool $current,
        public bool $legacy,
        public bool $rememberMe,
        public bool $hasAccessToken,
        public bool $hasRefreshToken,
        public int $tokenCount,
        public ?Carbon $createdAt,
        public ?Carbon $lastUsedAt,
        public ?Carbon $lastAccessUsedAt,
        public ?Carbon $lastRefreshUsedAt,
        public ?Carbon $accessTokenExpiresAt,
        public ?Carbon $refreshTokenExpiresAt,
        public ?string $deviceAlias,
        public ?string $deviceName,
        public ?string $browser,
        public ?string $os,
        public ?string $deviceType,
        public ?string $ipAddress,
    ) {}

    public function sortTimestamp(): int
    {
        return $this->lastUsedAt?->getTimestamp() ?? $this->createdAt?->getTimestamp() ?? 0;
    }
}
