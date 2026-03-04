<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\TenantContext;
use App\Domains\Shared\Auth\TenantScopedContext;
use App\Domains\Tenant\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait ResolvesTenantScopedContext
{
    /**
     * @param  string|list<string>  $permissionCode
     * @param  class-string  $policyModelClass
     */
    protected function resolveTenantScopedContextForModel(
        Request $request,
        string|array $permissionCode,
        string $ability,
        string $policyModelClass,
        TenantContextService $tenantContextService
    ): TenantScopedContext {
        if (is_array($permissionCode)) {
            /** @var list<string> $permissionCodes */
            $permissionCodes = array_values(
                array_filter(
                    array_map(static fn (mixed $code): string => trim((string) $code), $permissionCode),
                    static fn (string $code): bool => $code !== ''
                )
            );

            if ($permissionCodes === []) {
                return TenantScopedContext::failure(self::FORBIDDEN_CODE, 'Forbidden');
            }

            $authResult = $this->authenticateAndAuthorizeAny($request, 'access-api', $permissionCodes);
        } else {
            $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        }

        if ($authResult->failed()) {
            return TenantScopedContext::failure($authResult->code(), $authResult->message());
        }

        $authUser = $authResult->user();
        if (! $authUser instanceof User) {
            return TenantScopedContext::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $user = $authUser;
        if (! Gate::forUser($user)->allows($ability, $policyModelClass)) {
            return TenantScopedContext::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $tenantContext = TenantContext::fromPayload($tenantContextService->resolveTenantContext($request, $user));
        if ($tenantContext->failed()) {
            return TenantScopedContext::failure($tenantContext->code(), $tenantContext->message());
        }

        $tenantId = $tenantContext->tenantId();
        if (! is_int($tenantId) || $tenantId <= 0) {
            return TenantScopedContext::failure(self::PARAM_ERROR_CODE, 'Please select a tenant first');
        }

        return TenantScopedContext::success($user, $tenantId, $tenantContext->tenantName());
    }
}
