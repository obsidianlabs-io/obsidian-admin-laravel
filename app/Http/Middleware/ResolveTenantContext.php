<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Shared\Auth\ApiTokenResolver;
use App\Domains\Shared\Auth\RoleScopeContext;
use App\Domains\Shared\Auth\TenantContext;
use App\Domains\Shared\Auth\TenantOptionData;
use App\Domains\Tenant\Services\TenantContextService;
use App\Support\ApiErrorResponse;
use App\Support\ApiResultCode;
use App\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContextService $tenantContextService,
        private readonly ApiTokenResolver $tokenResolver
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authResult = $this->tokenResolver->resolveFromRequestIfPresent($request, 'access-api');

        if ($authResult->failed()) {
            // Pass through on failure (public routes, unauthenticated requests)
            return $next($request);
        }

        $tokenable = $authResult->user();
        if ($tokenable === null) {
            return $next($request);
        }

        $tenantContext = $this->tenantContextService->resolveTenantContext($request, $tokenable);
        $roleScope = $this->tenantContextService->resolveRoleScope($request, $tokenable);
        if ($tenantContext->failed()) {
            return ApiErrorResponse::json(
                $request,
                $tenantContext->code(),
                $tenantContext->message(),
                [],
                $this->resolveHttpStatus($tenantContext->code())
            );
        }
        if ($roleScope->failed()) {
            return ApiErrorResponse::json(
                $request,
                $roleScope->code(),
                $roleScope->message(),
                [],
                $this->resolveHttpStatus($roleScope->code())
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

        // Note: Does NOT update last_used_at (same as original behavior).
        // AuthenticateApiToken will handle that when it runs next.

        return $next($request);
    }

    private function resolveHttpStatus(string $code): int
    {
        $enum = ApiResultCode::tryFrom($code);

        return $enum?->httpStatus() ?? 200;
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
            'tenants' => array_map(
                static fn (TenantOptionData $tenant): array => $tenant->toArray(),
                $tenantContext->tenants(),
            ),
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
