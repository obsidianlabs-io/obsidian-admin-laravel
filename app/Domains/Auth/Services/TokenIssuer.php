<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\Results\TokenPairResult;
use Illuminate\Support\Str;

final class TokenIssuer
{
    public function __construct(private readonly SessionTokenAbilityCodec $abilityCodec) {}

    public function issueTokenPair(
        User $user,
        bool $rememberMe = false,
        ?string $sessionId = null,
        ?SessionClientContextData $sessionClientContext = null
    ): TokenPairResult {
        $resolvedSessionId = $this->abilityCodec->normalizeSessionId($sessionId) ?? (string) Str::uuid();
        $resolvedContext = $sessionClientContext ?? new SessionClientContextData;

        if ((bool) config('security.auth_tokens.single_device_login', true)) {
            $user->tokens()
                ->whereIn('name', [
                    SessionTokenAbilityCodec::ACCESS_TOKEN_NAME,
                    SessionTokenAbilityCodec::REFRESH_TOKEN_NAME,
                ])
                ->delete();
        }

        $accessTokenTtl = $rememberMe
            ? (int) config('api.auth_tokens.remember_access_ttl_minutes', 240)
            : (int) config('api.auth_tokens.access_ttl_minutes', 120);
        $refreshTokenTtl = $rememberMe
            ? (int) config('api.auth_tokens.remember_refresh_ttl_days', 30)
            : (int) config('api.auth_tokens.refresh_ttl_days', 7);

        $clientContextAbilities = $this->abilityCodec->buildSessionClientContextAbilities($resolvedContext);

        $accessAbilities = array_merge(
            [
                'access-api',
                $this->abilityCodec->sessionAbility($resolvedSessionId),
                $this->abilityCodec->accessTokenKindAbility(),
            ],
            $clientContextAbilities
        );
        $refreshAbilities = array_merge(
            [
                'refresh-token',
                $this->abilityCodec->sessionAbility($resolvedSessionId),
                $this->abilityCodec->refreshTokenKindAbility(),
            ],
            $clientContextAbilities
        );

        if ($rememberMe) {
            $accessAbilities[] = 'remember-me';
            $refreshAbilities[] = 'remember-me';
        }

        $accessToken = $user->createToken(
            SessionTokenAbilityCodec::ACCESS_TOKEN_NAME,
            $accessAbilities,
            now()->addMinutes(max(1, $accessTokenTtl))
        );

        $refreshToken = $user->createToken(
            SessionTokenAbilityCodec::REFRESH_TOKEN_NAME,
            $refreshAbilities,
            now()->addDays(max(1, $refreshTokenTtl))
        );

        return new TokenPairResult(
            token: $accessToken->plainTextToken,
            refreshToken: $refreshToken->plainTextToken
        );
    }
}
