<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Actions\Results\RefreshTokenResult;
use App\Domains\Auth\Services\AuthUserStateGuard;
use App\Domains\Auth\Services\SessionProjector;
use App\Domains\Auth\Services\TokenIssuer;
use App\Domains\Auth\Services\UserAgentParser;
use App\DTOs\Auth\RefreshTokenInputDTO;
use Laravel\Sanctum\PersonalAccessToken;

final class RefreshTokenAction
{
    private const UNAUTHORIZED_CODE = '8888';

    public function __construct(
        private readonly SessionProjector $sessionProjector,
        private readonly TokenIssuer $tokenIssuer,
        private readonly UserAgentParser $userAgentParser,
        private readonly AuthUserStateGuard $authUserStateGuard,
    ) {}

    public function handle(RefreshTokenInputDTO $input, ?string $userAgent, ?string $ipAddress): RefreshTokenResult
    {
        $token = PersonalAccessToken::findToken($input->refreshToken);

        if (! $token || ! $token->can('refresh-token')) {
            return RefreshTokenResult::failure(self::UNAUTHORIZED_CODE, 'Refresh token is invalid');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return RefreshTokenResult::failure(self::UNAUTHORIZED_CODE, 'Refresh token has expired');
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User || $tokenable->status !== '1') {
            return RefreshTokenResult::failure(self::UNAUTHORIZED_CODE, 'Refresh token is invalid');
        }

        if ($this->authUserStateGuard->isTenantUserWithInactiveTenant($tokenable)) {
            $token->delete();

            return RefreshTokenResult::failure(self::UNAUTHORIZED_CODE, 'Tenant is inactive');
        }

        if ($this->authUserStateGuard->isUserWithInactiveRole($tokenable)) {
            $token->delete();

            return RefreshTokenResult::failure(self::UNAUTHORIZED_CODE, 'Role is inactive');
        }

        $baseSessionClientContext = $this->sessionProjector->resolveSessionClientContextMetadata($token);
        $requestSessionClientContext = $this->userAgentParser->parse($userAgent, $ipAddress);
        $sessionClientContext = $baseSessionClientContext->merge($requestSessionClientContext);

        $sessionId = $this->sessionProjector->resolveSessionId($token);
        $rememberMe = $token->can('remember-me');

        $token->delete();

        return RefreshTokenResult::success(
            $this->tokenIssuer->issueTokenPair(
                user: $tokenable,
                rememberMe: $rememberMe,
                sessionId: $sessionId,
                sessionClientContext: $sessionClientContext,
            )
        );
    }
}
