<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Auth\ManagementContext;
use App\Domains\Shared\Auth\RoleScopeContext;
use App\Domains\Tenant\Services\TenantContextService;
use App\Support\ApiResultCode;
use Illuminate\Http\Request;

/**
 * Resolves the authenticated actor's role-management context.
 *
 * @property-read RoleScopeGuardService $roleScopeGuardService
 * @property-read TenantContextService $tenantContextService
 */
trait ResolvesRoleConsoleContext
{
    /**
     * Resolve the console context with authentication only (no permission check).
     */
    protected function resolveAuthenticatedRoleConsoleContext(Request $request): ManagementContext
    {
        return $this->resolveRoleConsoleContext(
            $request,
            $this->authenticate($request, 'access-api')
        );
    }

    /**
     * Resolve the console context with authentication + permission authorization.
     */
    protected function resolveAuthorizedRoleConsoleContext(Request $request, string $permissionCode): ManagementContext
    {
        return $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', $permissionCode)
        );
    }

    /**
     * Build the role-management context from an existing auth result.
     */
    protected function resolveRoleConsoleContext(Request $request, ApiAuthResult $authResult): ManagementContext
    {
        if ($authResult->failed()) {
            return ManagementContext::failure($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ManagementContext::failure(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }

        $actorLevel = $this->roleScopeGuardService->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return ManagementContext::failure(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        $roleScope = $this->tenantContextService->resolveRoleScope($request, $user);
        if ($roleScope->failed()) {
            return ManagementContext::failure($roleScope->code(), $roleScope->message());
        }

        return ManagementContext::success(
            user: $user,
            actorLevel: $actorLevel,
            tenantId: $roleScope->tenantId(),
            isSuper: $roleScope->isSuper()
        );
    }

    protected function resolveRoleScope(Request $request, User $user): RoleScopeContext
    {
        return $this->tenantContextService->resolveRoleScope($request, $user);
    }
}
