<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Support\ApiErrorResponse;
use App\Support\ApiResultCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissionCodes): Response
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return $this->error($request, ApiResultCode::UNAUTHORIZED->value, 'Unauthorized');
        }

        if ($permissionCodes === []) {
            return $next($request);
        }

        foreach ($permissionCodes as $permissionCode) {
            if ($user->hasPermission($permissionCode)) {
                return $next($request);
            }
        }

        return $this->error($request, ApiResultCode::FORBIDDEN->value, 'Forbidden');
    }

    private function error(Request $request, string $code, string $msg): Response
    {
        $httpStatus = $code === ApiResultCode::UNAUTHORIZED->value ? 401 : 403;

        return ApiErrorResponse::json($request, $code, $msg, [], $httpStatus);
    }
}
