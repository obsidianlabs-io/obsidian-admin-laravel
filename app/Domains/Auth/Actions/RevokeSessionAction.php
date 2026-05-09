<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Actions\Results\RevokeSessionActionResult;
use App\Domains\Auth\Services\SessionProjector;
use App\Support\ApiResultCode;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeSessionAction
{
    public function __construct(private readonly SessionProjector $sessionProjector) {}

    public function handle(
        User $user,
        string $sessionId,
        ?PersonalAccessToken $currentAccessToken = null,
    ): RevokeSessionActionResult {
        $result = $this->sessionProjector->revokeSession($user, $sessionId, $currentAccessToken);

        if ($result->deletedTokenCount() <= 0) {
            return RevokeSessionActionResult::failure(ApiResultCode::LOGIN_FAILED, 'Session not found');
        }

        return RevokeSessionActionResult::success(
            sessionId: $sessionId,
            deletedTokenCount: $result->deletedTokenCount(),
            revokedCurrentSession: $result->revokedCurrentSession(),
        );
    }
}
