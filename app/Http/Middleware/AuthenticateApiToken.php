<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Support\ApiDateTime;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    private const UNAUTHORIZED_CODE = '8888';

    private const TOKEN_EXPIRED_CODE = '9999';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $ability = 'access-api'): Response
    {
        $plainToken = $request->bearerToken();
        if (! $plainToken) {
            return $this->error($request, self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if (! $token || ! $token->can($ability)) {
            return $this->error($request, self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return $this->error($request, self::TOKEN_EXPIRED_CODE, 'Token expired');
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User) {
            return $this->error($request, self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if ($tokenable->status !== '1') {
            return $this->error($request, self::UNAUTHORIZED_CODE, 'User is inactive');
        }

        ApiDateTime::assignRequestTimezone($request, ApiDateTime::resolveUserTimezone($tokenable));
        $now = now();
        if (! $token->last_used_at || $token->last_used_at->lt($now->copy()->subMinute())) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        $request->attributes->set('auth_user', $tokenable);
        $request->attributes->set('auth_token', $token);

        return $next($request);
    }

    private function error(Request $request, string $code, string $msg): JsonResponse
    {
        return ApiErrorResponse::json($request, $code, $msg);
    }
}
