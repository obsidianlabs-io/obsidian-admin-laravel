<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Services\TenantContextService;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(private readonly TenantContextService $tenantContextService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (! $plainToken) {
            return $next($request);
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if (! $token || ! $token->can('access-api')) {
            return $next($request);
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return $next($request);
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User || $tokenable->status !== '1') {
            return $next($request);
        }

        $tenantContext = $this->tenantContextService->resolveTenantContext($request, $tokenable);
        $roleScope = $this->tenantContextService->resolveRoleScope($request, $tokenable);
        if (! $tenantContext['ok']) {
            return ApiErrorResponse::json(
                $request,
                $tenantContext['code'],
                $tenantContext['msg']
            );
        }
        if (! $roleScope['ok']) {
            return ApiErrorResponse::json(
                $request,
                $roleScope['code'],
                $roleScope['msg']
            );
        }

        $request->attributes->set('auth_user', $tokenable);
        $request->attributes->set('tenant_context', $tenantContext);
        $request->attributes->set('role_scope', $roleScope);
        Log::withContext([
            'user_id' => (int) $tokenable->id,
            'tenant_id' => $tenantContext['tenantId'] ?? null,
            'role_scope_tenant_id' => $roleScope['tenantId'] ?? null,
            'is_super_admin' => (bool) ($roleScope['isSuper'] ?? false),
        ]);

        return $next($request);
    }
}
