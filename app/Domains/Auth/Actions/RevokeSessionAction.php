<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Actions\Results\RevokeSessionActionResult;
use App\Domains\Auth\Services\SessionProjector;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeSessionAction
{
    private const PARAM_ERROR_CODE = '1001';

    public function __construct(private readonly SessionProjector $sessionProjector) {}

    public function handle(
        User $user,
        string $sessionId,
        ?PersonalAccessToken $currentAccessToken = null,
    ): RevokeSessionActionResult {
        $result = $this->sessionProjector->revokeSession($user, $sessionId, $currentAccessToken);

        if ($result->deletedTokenCount() <= 0) {
            return RevokeSessionActionResult::failure(self::PARAM_ERROR_CODE, 'Session not found');
        }

        return RevokeSessionActionResult::success(
            sessionId: $sessionId,
            deletedTokenCount: $result->deletedTokenCount(),
            revokedCurrentSession: $result->revokedCurrentSession(),
        );
    }
}
