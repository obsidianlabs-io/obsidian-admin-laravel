<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

use App\Domains\Access\Models\User;
use App\Support\ApiDateTime;
use App\Support\ApiResultCode;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Centralized API token resolution service.
 *
 * Encapsulates the shared 7-step token validation logic that was previously
 * duplicated across AuthenticateApiToken, ResolveTenantContext, and ApiController.
 *
 * Results are cached on the request attributes to prevent redundant DB queries
 * within the same request lifecycle.
 */
final class ApiTokenResolver
{
    private const ATTR_AUTH_RESULT = '_api_auth_result';

    private const ATTR_LAST_USED_TOUCHED = '_api_auth_last_used_touched';

    /**
     * Resolve the authenticated user from the request, always performing
     * resolution if no cached result exists.
     *
     * Used by AuthenticateApiToken middleware and ApiController::authenticate().
     */
    public function resolveFromRequest(Request $request, string $ability = 'access-api'): ApiAuthResult
    {
        $cached = $request->attributes->get(self::ATTR_AUTH_RESULT);
        if ($cached instanceof ApiAuthResult) {
            return $cached;
        }

        $result = $this->resolve($request, $ability);
        $request->attributes->set(self::ATTR_AUTH_RESULT, $result);

        return $result;
    }

    /**
     * Resolve the authenticated user from the request only if a bearer token
     * is present. Returns a failure result without DB queries when no token
     * is provided, allowing pass-through middleware behavior.
     *
     * Used by ResolveTenantContext middleware for routes that may be public.
     */
    public function resolveFromRequestIfPresent(Request $request, string $ability = 'access-api'): ApiAuthResult
    {
        $cached = $request->attributes->get(self::ATTR_AUTH_RESULT);
        if ($cached instanceof ApiAuthResult) {
            return $cached;
        }

        if ($request->bearerToken() === null || $request->bearerToken() === '') {
            $result = ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
            $request->attributes->set(self::ATTR_AUTH_RESULT, $result);

            return $result;
        }

        $result = $this->resolve($request, $ability);
        $request->attributes->set(self::ATTR_AUTH_RESULT, $result);

        return $result;
    }

    /**
     * Update last_used_at on the token, throttled to once per minute.
     *
     * Should be called exactly once per request by the first middleware
     * that fully validates. The _api_auth_last_used_touched flag ensures
     * this is never called more than once even when multiple middleware
     * and controller methods run in sequence.
     */
    public function touchLastUsedAt(Request $request, PersonalAccessToken $token): void
    {
        if ($request->attributes->get(self::ATTR_LAST_USED_TOUCHED) === true) {
            return;
        }

        $now = now();
        if ($token->last_used_at === null || $token->last_used_at->lt($now->copy()->subMinute())) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        $request->attributes->set(self::ATTR_LAST_USED_TOUCHED, true);
    }

    /**
     * Core 7-step token resolution logic.
     *
     * 1. Extract bearer token from request
     * 2. Find token via PersonalAccessToken::findToken()
     * 3. Check token ability
     * 4. Check token expiration (delete if expired)
     * 5. Verify tokenable is a User instance
     * 6. Check user status is active
     * 7. Load user's role and check role status is active
     */
    private function resolve(Request $request, string $ability): ApiAuthResult
    {
        $plainToken = $request->bearerToken();
        if ($plainToken === null || $plainToken === '') {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if ($token === null || ! $token->can($ability)) {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return ApiAuthResult::failure(ApiResultCode::TOKEN_EXPIRED, 'Token expired');
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User) {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        if ($tokenable->status !== '1') {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'User is inactive');
        }

        $tokenable->loadMissing('role:id,code,name,level,status,tenant_id');
        $role = $tokenable->getRelationValue('role');
        if ($role === null || (string) $role->status !== '1') {
            return ApiAuthResult::failure(ApiResultCode::UNAUTHORIZED, 'Role is inactive');
        }

        ApiDateTime::assignRequestTimezone($request, ApiDateTime::resolveUserTimezone($tokenable));

        return ApiAuthResult::success($tokenable, $token);
    }
}
