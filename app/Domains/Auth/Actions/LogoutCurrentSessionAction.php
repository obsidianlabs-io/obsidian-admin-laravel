<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\SessionProjector;
use Laravel\Sanctum\PersonalAccessToken;

final class LogoutCurrentSessionAction
{
    public function __construct(private readonly SessionProjector $sessionProjector) {}

    public function handle(User $user, PersonalAccessToken $accessToken, ?string $refreshToken): void
    {
        $sessionId = $this->sessionProjector->resolveSessionId($accessToken);

        if ($sessionId !== null) {
            $this->sessionProjector->revokeSession($user, $sessionId, $accessToken);

            return;
        }

        $accessToken->delete();

        if (! is_string($refreshToken) || $refreshToken === '') {
            return;
        }

        $refreshTokenRecord = PersonalAccessToken::findToken($refreshToken);
        if (
            $refreshTokenRecord
            && $refreshTokenRecord->tokenable_type === User::class
            && $refreshTokenRecord->tokenable_id === $user->id
        ) {
            $refreshTokenRecord->delete();
        }
    }
}
