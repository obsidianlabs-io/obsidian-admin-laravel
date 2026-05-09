<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Support\ApiResultCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait ResolvesPlatformConsoleContext
{
    /**
     * Resolve a platform-only management context:
     * - authenticated
     * - permission granted
     * - policy allows
     * - no tenant selected (platform scope only)
     *
     * @param  class-string  $policyModelClass
     */
    protected function resolvePlatformConsoleContext(
        Request $request,
        string $permissionCode,
        string $policyAbility,
        string $policyModelClass,
        string $tenantSelectedMessage
    ): ApiAuthResult {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        if (! Gate::forUser($user)->allows($policyAbility, $policyModelClass)) {
            return ApiAuthResult::failure(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        $selectedTenantRaw = trim((string) $request->header('X-Tenant-Id', '0'));
        $selectedTenantId = ctype_digit($selectedTenantRaw) ? (int) $selectedTenantRaw : 0;
        if ($selectedTenantId > 0) {
            return ApiAuthResult::failure(ApiResultCode::FORBIDDEN, $tenantSelectedMessage);
        }

        return $authResult;
    }
}
