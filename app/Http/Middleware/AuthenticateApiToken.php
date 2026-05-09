<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Auth\ApiTokenResolver;
use App\Support\ApiErrorResponse;
use App\Support\ApiResultCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function __construct(private readonly ApiTokenResolver $tokenResolver) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $ability = 'access-api'): Response
    {
        $authResult = $this->tokenResolver->resolveFromRequest($request, $ability);

        if ($authResult->failed()) {
            $httpStatus = $authResult->code() === ApiResultCode::TOKEN_EXPIRED->value ? 401 : 401;

            return $this->error($request, $authResult->code(), $authResult->message(), $httpStatus);
        }

        $user = $authResult->user();
        $token = $authResult->requireToken();

        $this->tokenResolver->touchLastUsedAt($request, $token);

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_token', $token);

        return $next($request);
    }

    private function error(Request $request, string $code, string $msg, int $httpStatus = 401): JsonResponse
    {
        return ApiErrorResponse::json($request, $code, $msg, [], $httpStatus);
    }
}
