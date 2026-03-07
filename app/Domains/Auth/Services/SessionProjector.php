<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\Results\RevokeSessionResult;
use App\Domains\Auth\Services\Results\SessionRecord;
use App\Domains\Auth\Services\Results\SessionRecordsResult;
use App\Domains\Auth\Services\Results\UpdateSessionAliasResult;
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

    public function listSessions(User $user, ?PersonalAccessToken $currentAccessToken = null): SessionRecordsResult
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

        /** @var array<string, SessionRecordBuilder> $groups */
        $groups = [];

        foreach ($tokens as $token) {
            $sessionKey = $this->abilityCodec->sessionKeyForToken($token);
            $sessionId = $this->abilityCodec->resolveSessionId($token) ?? $sessionKey;
            $isLegacy = str_starts_with($sessionKey, 'legacy:');
            $tokenKind = $this->abilityCodec->resolveTokenKind($token);
            $rememberMe = $token->can('remember-me');
            $clientContext = $this->abilityCodec->resolveSessionClientContext($token);

            if (! isset($groups[$sessionKey])) {
                $groups[$sessionKey] = new SessionRecordBuilder(
                    sessionId: $sessionId,
                    current: $currentSessionKey !== null && hash_equals($currentSessionKey, $sessionKey),
                    legacy: $isLegacy
                );
            }

            $groups[$sessionKey]->applyToken($token, $tokenKind, $rememberMe, $clientContext);
        }

        $records = array_values(array_map(
            fn (SessionRecordBuilder $builder): SessionRecord => $builder->toRecord($this->userAgentParser),
            $groups
        ));

        usort($records, static function (SessionRecord $a, SessionRecord $b): int {
            return $b->sortTimestamp() <=> $a->sortTimestamp();
        });

        return new SessionRecordsResult($records);
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
}
