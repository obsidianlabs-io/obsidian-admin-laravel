<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Actions\Results\UpdateSessionAliasActionResult;
use App\Domains\Auth\Services\SessionProjector;
use App\Support\ApiResultCode;
use Laravel\Sanctum\PersonalAccessToken;

final class UpdateSessionAliasAction
{
    public function __construct(private readonly SessionProjector $sessionProjector) {}

    public function handle(
        User $user,
        string $sessionId,
        ?string $deviceAlias,
        ?PersonalAccessToken $currentAccessToken = null,
    ): UpdateSessionAliasActionResult {
        $result = $this->sessionProjector->updateSessionAlias(
            $user,
            $sessionId,
            $deviceAlias,
            $currentAccessToken,
        );

        if ($result->updatedTokenCount() <= 0) {
            return UpdateSessionAliasActionResult::failure(ApiResultCode::LOGIN_FAILED, 'Session not found');
        }

        return UpdateSessionAliasActionResult::success(
            sessionId: $sessionId,
            deviceAlias: $result->deviceAlias(),
            updatedTokenCount: $result->updatedTokenCount(),
            updatedCurrentSession: $result->updatedCurrentSession(),
        );
    }
}
