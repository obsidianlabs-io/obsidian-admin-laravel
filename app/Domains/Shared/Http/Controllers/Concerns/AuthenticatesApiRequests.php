<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Auth\ApiTokenResolver;
use App\Support\ApiResultCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait AuthenticatesApiRequests
{
    protected function authenticate(Request $request, string $ability): ApiAuthResult
    {
        $tokenResolver = app(ApiTokenResolver::class);
        $authResult = $tokenResolver->resolveFromRequest($request, $ability);

        if ($authResult->failed()) {
            return $authResult;
        }

        $token = $authResult->token();
        if ($token !== null) {
            $tokenResolver->touchLastUsedAt($request, $token);
        }

        return $authResult;
    }

    protected function authenticateAndAuthorize(Request $request, string $ability, string $permissionCode): ApiAuthResult
    {
        $authResult = $this->authenticate($request, $ability);

        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        if (! Gate::forUser($user)->allows('access-permission', $permissionCode)) {
            return ApiAuthResult::failure(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        return $authResult;
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    protected function authenticateAndAuthorizeAny(Request $request, string $ability, array $permissionCodes): ApiAuthResult
    {
        $authResult = $this->authenticate($request, $ability);

        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        foreach ($permissionCodes as $permissionCode) {
            if (Gate::forUser($user)->allows('access-permission', $permissionCode)) {
                return $authResult;
            }
        }

        return ApiAuthResult::failure(ApiResultCode::FORBIDDEN, 'Forbidden');
    }

    protected function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('role:id,code,name,level,status');

        $roleCode = '';
        if ($user->role instanceof Role) {
            $roleCode = (string) $user->role->code;
        }

        return $roleCode === 'R_SUPER';
    }
}
