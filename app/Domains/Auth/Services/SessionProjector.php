<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\Results\RevokeSessionResult;
use App\Domains\Auth\Services\Results\UpdateSessionAliasResult;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionProjector
{
    public function __construct(
        private readonly SessionTokenAbilityCodec $abilityCodec,
        private readonly UserAgentParser $userAgentParser
    ) {}

    public function resolveSessionId(PersonalAccessToken $token): ?string
    {
        return $this->abilityCodec->resolveSessionId($token);
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
        $tokens = $this->managedTokensQuery($user)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'name', 'abilities', 'created_at', 'last_used_at', 'expires_at']);

        $currentSessionKey = null;
        if ($currentAccessToken) {
            $currentSessionKey = $this->abilityCodec->sessionKeyForToken($currentAccessToken);
        }

        /** @var array<string, array{
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
         * }> $groups
         */
        $groups = [];

        foreach ($tokens as $token) {
            $sessionKey = $this->abilityCodec->sessionKeyForToken($token);
            $sessionId = $this->abilityCodec->resolveSessionId($token) ?? $sessionKey;
            $isLegacy = str_starts_with($sessionKey, 'legacy:');
            $tokenKind = $this->abilityCodec->resolveTokenKind($token);
            $rememberMe = $token->can('remember-me');
            $clientContext = $this->abilityCodec->resolveSessionClientContext($token);

            if (! isset($groups[$sessionKey])) {
                $groups[$sessionKey] = [
                    'sessionId' => $sessionId,
                    'current' => $currentSessionKey !== null && hash_equals($currentSessionKey, $sessionKey),
                    'legacy' => $isLegacy,
                    'rememberMe' => $rememberMe,
                    'hasAccessToken' => false,
                    'hasRefreshToken' => false,
                    'tokenCount' => 0,
                    'createdAt' => null,
                    'lastUsedAt' => null,
                    'lastAccessUsedAt' => null,
                    'lastRefreshUsedAt' => null,
                    'accessTokenExpiresAt' => null,
                    'refreshTokenExpiresAt' => null,
                    'deviceAlias' => null,
                    'deviceName' => null,
                    'browser' => null,
                    'os' => null,
                    'deviceType' => null,
                    'ipAddress' => null,
                ];
            }

            $group = &$groups[$sessionKey];
            $group['tokenCount']++;
            $group['rememberMe'] = $group['rememberMe'] || $rememberMe;

            if ($token->created_at && (! $group['createdAt'] || $token->created_at->lt($group['createdAt']))) {
                $group['createdAt'] = $token->created_at;
            }

            if ($token->last_used_at && (! $group['lastUsedAt'] || $token->last_used_at->gt($group['lastUsedAt']))) {
                $group['lastUsedAt'] = $token->last_used_at;
            }

            if ($tokenKind === 'access') {
                $group['hasAccessToken'] = true;
                $group['accessTokenExpiresAt'] = $this->preferLaterExpiry($group['accessTokenExpiresAt'], $token->expires_at);
                if ($token->last_used_at && (! $group['lastAccessUsedAt'] || $token->last_used_at->gt($group['lastAccessUsedAt']))) {
                    $group['lastAccessUsedAt'] = $token->last_used_at;
                }
            } elseif ($tokenKind === 'refresh') {
                $group['hasRefreshToken'] = true;
                $group['refreshTokenExpiresAt'] = $this->preferLaterExpiry($group['refreshTokenExpiresAt'], $token->expires_at);
                if ($token->last_used_at && (! $group['lastRefreshUsedAt'] || $token->last_used_at->gt($group['lastRefreshUsedAt']))) {
                    $group['lastRefreshUsedAt'] = $token->last_used_at;
                }
            }

            $group['deviceName'] = $this->preferClientContextValue($group['deviceName'], $clientContext->deviceName, $tokenKind);
            $group['deviceAlias'] = $this->preferClientContextValue($group['deviceAlias'], $clientContext->deviceAlias, $tokenKind);
            $group['browser'] = $this->preferClientContextValue($group['browser'], $clientContext->browser, $tokenKind);
            $group['os'] = $this->preferClientContextValue($group['os'], $clientContext->os, $tokenKind);
            $group['deviceType'] = $this->preferClientContextValue($group['deviceType'], $clientContext->deviceType, $tokenKind);
            $group['ipAddress'] = $this->preferClientContextValue($group['ipAddress'], $clientContext->ipAddress, $tokenKind);
            unset($group);
        }

        $records = array_values($groups);

        foreach ($records as &$record) {
            if (! is_string($record['deviceName']) || trim($record['deviceName']) === '') {
                $record['deviceName'] = $this->userAgentParser->buildDeviceNameFromParts(
                    is_string($record['browser']) ? $record['browser'] : null,
                    is_string($record['os']) ? $record['os'] : null,
                    is_string($record['deviceType']) ? $record['deviceType'] : null
                );
            }
        }
        unset($record);

        usort($records, static function (array $a, array $b): int {
            $aTimestamp = $a['lastUsedAt']?->getTimestamp() ?? $a['createdAt']?->getTimestamp() ?? 0;
            $bTimestamp = $b['lastUsedAt']?->getTimestamp() ?? $b['createdAt']?->getTimestamp() ?? 0;

            return $bTimestamp <=> $aTimestamp;
        });

        return $records;
    }

    public function updateSessionAlias(
        User $user,
        string $sessionId,
        ?string $alias,
        ?PersonalAccessToken $currentAccessToken = null
    ): UpdateSessionAliasResult {
        $normalizedSessionId = $this->abilityCodec->normalizeSessionId($sessionId);
        $normalizedAlias = SessionClientContextData::sanitize($alias, 80);
        $currentSessionKey = $currentAccessToken ? $this->abilityCodec->sessionKeyForToken($currentAccessToken) : null;
        $updatedTokenCount = 0;
        $updatedCurrentSession = false;

        $tokens = $this->managedTokensQuery($user)->get();

        foreach ($tokens as $token) {
            $tokenSessionKey = $this->abilityCodec->sessionKeyForToken($token);
            $tokenSessionId = $this->abilityCodec->resolveSessionId($token) ?? $tokenSessionKey;

            if (
                ($normalizedSessionId !== null && $tokenSessionId === $normalizedSessionId)
                || ($normalizedSessionId === null && $tokenSessionKey === $sessionId)
            ) {
                $abilities = $this->abilityCodec->withUpdatedDeviceAlias(
                    $this->abilityCodec->resolveAbilities($token),
                    $normalizedAlias
                );

                $token->abilities = $abilities;
                $token->save();
                $updatedTokenCount++;

                if ($currentSessionKey !== null && hash_equals($currentSessionKey, $tokenSessionKey)) {
                    $updatedCurrentSession = true;
                }
            }
        }

        return new UpdateSessionAliasResult(
            updatedTokenCount: $updatedTokenCount,
            updatedCurrentSession: $updatedCurrentSession,
            deviceAlias: $normalizedAlias
        );
    }

    public function resolveSessionClientContextMetadata(PersonalAccessToken $token): SessionClientContextData
    {
        return $this->abilityCodec->resolveSessionClientContext($token);
    }

    public function revokeSession(
        User $user,
        string $sessionId,
        ?PersonalAccessToken $currentAccessToken = null
    ): RevokeSessionResult {
        $normalizedSessionId = $this->abilityCodec->normalizeSessionId($sessionId);
        $currentSessionKey = $currentAccessToken ? $this->abilityCodec->sessionKeyForToken($currentAccessToken) : null;
        $deletedTokenCount = 0;
        $revokedCurrentSession = false;

        $tokens = $this->managedTokensQuery($user)->get();

        foreach ($tokens as $token) {
            $tokenSessionKey = $this->abilityCodec->sessionKeyForToken($token);
            $tokenSessionId = $this->abilityCodec->resolveSessionId($token) ?? $tokenSessionKey;

            if (
                ($normalizedSessionId !== null && $tokenSessionId === $normalizedSessionId)
                || ($normalizedSessionId === null && $tokenSessionKey === $sessionId)
            ) {
                $deletedTokenCount++;
                if ($currentSessionKey !== null && hash_equals($currentSessionKey, $tokenSessionKey)) {
                    $revokedCurrentSession = true;
                }
                $token->delete();
            }
        }

        return new RevokeSessionResult(
            deletedTokenCount: $deletedTokenCount,
            revokedCurrentSession: $revokedCurrentSession
        );
    }

    /**
     * @return MorphMany<PersonalAccessToken, User>
     */
    private function managedTokensQuery(User $user): MorphMany
    {
        return $user->tokens()->whereIn('name', [
            SessionTokenAbilityCodec::ACCESS_TOKEN_NAME,
            SessionTokenAbilityCodec::REFRESH_TOKEN_NAME,
        ]);
    }

    private function preferLaterExpiry(?Carbon $current, ?Carbon $incoming): ?Carbon
    {
        if (! $current instanceof Carbon) {
            return $incoming;
        }

        if (! $incoming instanceof Carbon) {
            return $current;
        }

        return $incoming->gt($current) ? $incoming : $current;
    }

    private function preferClientContextValue(?string $current, ?string $incoming, string $tokenKind): ?string
    {
        if ($incoming === null || trim($incoming) === '') {
            return $current;
        }

        if ($current === null || trim($current) === '') {
            return $incoming;
        }

        if ($tokenKind === 'access') {
            return $incoming;
        }

        return $current;
    }
}
