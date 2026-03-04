<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\RoleScopeContext;
use App\Domains\Shared\Auth\TenantContext;
use App\Domains\Tenant\Services\TenantContextService;
use App\Support\ApiErrorResponse;
use App\Support\RequestContext;
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

        $tokenable->loadMissing('role:id,code,name,level,status,tenant_id');
        $role = $tokenable->getRelationValue('role');
        if (! $role || (string) $role->status !== '1') {
            return $next($request);
        }

        $tenantContext = $this->tenantContextService->resolveTenantContext($request, $tokenable);
        $roleScope = $this->tenantContextService->resolveRoleScope($request, $tokenable);
        if ($tenantContext->failed()) {
            return ApiErrorResponse::json(
                $request,
                $tenantContext->code(),
                $tenantContext->message()
            );
        }
        if ($roleScope->failed()) {
            return ApiErrorResponse::json(
                $request,
                $roleScope->code(),
                $roleScope->message()
            );
        }

        $request->attributes->set('auth_user', $tokenable);
        $request->attributes->set('tenant_context', $this->serializeTenantContext($tenantContext));
        $request->attributes->set('role_scope', $this->serializeRoleScopeContext($roleScope));
        RequestContext::add([
            RequestContext::KEY_USER_ID => (int) $tokenable->id,
            RequestContext::KEY_TENANT_ID => $tenantContext->tenantId(),
            RequestContext::KEY_ROLE_SCOPE_TENANT_ID => $roleScope->tenantId(),
            RequestContext::KEY_IS_SUPER_ADMIN => $roleScope->isSuper(),
        ]);
        Log::withContext([
            'user_id' => (int) $tokenable->id,
            'tenant_id' => $tenantContext->tenantId(),
            'role_scope_tenant_id' => $roleScope->tenantId(),
            'is_super_admin' => $roleScope->isSuper(),
        ]);

        return $next($request);
    }

    /**
     * @return array{
     *   ok: true,
     *   code: string,
     *   msg: string,
     *   tenantId: int|null,
     *   tenantName: string,
     *   tenants: list<array{tenantId: string, tenantName: string}>
     * }
     */
    private function serializeTenantContext(TenantContext $tenantContext): array
    {
        return [
            'ok' => true,
            'code' => $tenantContext->code(),
            'msg' => $tenantContext->message(),
            'tenantId' => $tenantContext->tenantId(),
            'tenantName' => $tenantContext->tenantName(),
            'tenants' => $tenantContext->tenants(),
        ];
    }

    /**
     * @return array{
     *   ok: true,
     *   code: string,
     *   msg: string,
     *   tenantId: int|null,
     *   isSuper: bool
     * }
     */
    private function serializeRoleScopeContext(RoleScopeContext $roleScope): array
    {
        return [
            'ok' => true,
            'code' => $roleScope->code(),
            'msg' => $roleScope->message(),
            'tenantId' => $roleScope->tenantId(),
            'isSuper' => $roleScope->isSuper(),
        ];
    }
}
