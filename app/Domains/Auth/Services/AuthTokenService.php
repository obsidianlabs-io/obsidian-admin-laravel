<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\Results\RevokeSessionResult;
use App\Domains\Auth\Services\Results\TokenPairResult;
use App\Domains\Auth\Services\Results\UpdateSessionAliasResult;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthTokenService
{
    public const ACCESS_TOKEN_NAME = SessionTokenAbilityCodec::ACCESS_TOKEN_NAME;

    public const REFRESH_TOKEN_NAME = SessionTokenAbilityCodec::REFRESH_TOKEN_NAME;

    public function __construct(
        private readonly TokenIssuer $tokenIssuer,
        private readonly SessionProjector $sessionProjector,
        private readonly UserAgentParser $userAgentParser
    ) {}

    public function issueTokenPair(
        User $user,
        bool $rememberMe = false,
        ?string $sessionId = null,
        ?SessionClientContextData $sessionClientContext = null
    ): TokenPairResult {
        return $this->tokenIssuer->issueTokenPair(
            user: $user,
            rememberMe: $rememberMe,
            sessionId: $sessionId,
            sessionClientContext: $sessionClientContext
        );
    }

    public function resolveSessionId(PersonalAccessToken $token): ?string
    {
        return $this->sessionProjector->resolveSessionId($token);
    }

    /**
     * @return list<array{
     *   sessionId: string,
     *   current: bool,
     *   legacy: bool,
     *   rememberMe: bool,
     *   hasAccessToken: bool,
     *   hasRefreshToken: bool,
     *   tokenCount: int,
     *   createdAt: ?\Illuminate\Support\Carbon,
     *   lastUsedAt: ?\Illuminate\Support\Carbon,
     *   lastAccessUsedAt: ?\Illuminate\Support\Carbon,
     *   lastRefreshUsedAt: ?\Illuminate\Support\Carbon,
     *   accessTokenExpiresAt: ?\Illuminate\Support\Carbon,
     *   refreshTokenExpiresAt: ?\Illuminate\Support\Carbon,
     *   deviceAlias: ?string,
     *   deviceName: ?string,
     *   browser: ?string,
     *   os: ?string,
     *   deviceType: ?string,
     *   ipAddress: ?string
     * }>
     */
    public function listSessions(User $user, ?PersonalAccessToken $currentAccessToken = null): array
    {
        return $this->sessionProjector->listSessions($user, $currentAccessToken);
    }

    public function updateSessionAlias(
        User $user,
        string $sessionId,
        ?string $alias,
        ?PersonalAccessToken $currentAccessToken = null
    ): UpdateSessionAliasResult {
        return $this->sessionProjector->updateSessionAlias($user, $sessionId, $alias, $currentAccessToken);
    }

    public function buildSessionClientContext(?string $userAgent, ?string $ipAddress): SessionClientContextData
    {
        return $this->userAgentParser->parse($userAgent, $ipAddress);
    }

    public function resolveSessionClientContextMetadata(PersonalAccessToken $token): SessionClientContextData
    {
        return $this->sessionProjector->resolveSessionClientContextMetadata($token);
    }

    public function revokeSession(
        User $user,
        string $sessionId,
        ?PersonalAccessToken $currentAccessToken = null
    ): RevokeSessionResult {
        return $this->sessionProjector->revokeSession($user, $sessionId, $currentAccessToken);
    }
}
