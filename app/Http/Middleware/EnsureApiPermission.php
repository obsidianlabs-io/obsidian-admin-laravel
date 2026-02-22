<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiPermission
{
    private const FORBIDDEN_CODE = '1003';

    private const UNAUTHORIZED_CODE = '8888';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissionCodes): Response
    {
        $user = $request->attributes->get('auth_user');
        if (! $user instanceof User) {
            return $this->error($request, self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if ($permissionCodes === []) {
            return $next($request);
        }

        foreach ($permissionCodes as $permissionCode) {
            if ($user->hasPermission($permissionCode)) {
                return $next($request);
            }
        }

        return $this->error($request, self::FORBIDDEN_CODE, 'Forbidden');
    }

    private function error(Request $request, string $code, string $msg): Response
    {
        return ApiErrorResponse::json($request, $code, $msg);
    }
}
