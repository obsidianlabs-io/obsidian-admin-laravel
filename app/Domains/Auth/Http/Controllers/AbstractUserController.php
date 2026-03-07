<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Access\Services\UserService;
use App\Domains\Auth\Actions\ResolveUserContextAction;
use App\Domains\Auth\Actions\ResolveUserInfoAction;
use App\Domains\Auth\Actions\Results\ResolvedUserProfile;
use App\Domains\Auth\Actions\Results\ResolvedUserRoles;
use App\Domains\Auth\Http\Controllers\Concerns\ResolvesRoleScope;
use App\Domains\Auth\Http\Controllers\Concerns\VerifiesTotpCode;
use App\Domains\Auth\Services\MenuMetadataService;
use App\Domains\Auth\Services\TotpService;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Auth\ManagementContext;
use App\Domains\Shared\Auth\TenantContext;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\AuditLogService;
use App\Domains\System\Services\ThemeConfigService;
use App\Domains\Tenant\Services\TenantContextService;
use Illuminate\Http\Request;

abstract class AbstractUserController extends ApiController
{
    use ResolvesRoleScope;
    use VerifiesTotpCode;

    public function __construct(
        protected readonly UserService $userService,
        protected readonly TenantContextService $tenantContextService,
        protected readonly MenuMetadataService $menuMetadataService,
        protected readonly ThemeConfigService $themeConfigService,
        protected readonly AuditLogService $auditLogService,
        protected readonly TotpService $totpService,
        protected readonly ResolveUserContextAction $resolveUserContext,
        protected readonly ResolveUserInfoAction $resolveUserInfo,
    ) {}

    protected function resolveRoles(User $user): ResolvedUserRoles
    {
        return $this->resolveUserContext->resolveRoles($user);
    }

    protected function resolveProfile(User $user): ResolvedUserProfile
    {
        return $this->resolveUserContext->resolveProfile($user);
    }

    protected function resolveLocale(User $user): string
    {
        return $this->resolveUserContext->resolveLocale($user);
    }

    protected function resolveThemeSchema(User $user): ?string
    {
        return $this->resolveUserContext->resolveThemeSchema($user);
    }

    protected function resolveTimezone(User $user): string
    {
        return $this->resolveUserContext->resolveTimezone($user);
    }

    /**
     * @param  string|list<string>  $permissionCode
     */
    protected function resolveUserManagementContext(Request $request, string|array $permissionCode): ManagementContext
    {
        if (is_array($permissionCode)) {
            $permissionCodes = array_map(static fn (string $code): string => (string) $code, $permissionCode);
            $authResult = $this->authenticateAndAuthorizeAny($request, 'access-api', $permissionCodes);
        } else {
            $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        }

        if ($authResult->failed()) {
            return ManagementContext::failure($authResult->code(), $authResult->message());
        }

        $authUser = $authResult->user();
        if (! $authUser instanceof User) {
            return ManagementContext::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $actorLevel = $this->resolveUserRoleLevel($authUser);
        if ($actorLevel <= 0) {
            return ManagementContext::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $tenantContext = $this->resolveTenantContext($request, $authUser);
        if ($tenantContext->failed()) {
            return ManagementContext::failure($tenantContext->code(), $tenantContext->message());
        }

        return ManagementContext::success(
            user: $authUser,
            actorLevel: $actorLevel,
            tenantId: $tenantContext->tenantId(),
            isSuper: $this->isSuperAdmin($authUser)
        );
    }

    protected function resolveTenantContext(Request $request, User $user): TenantContext
    {
        return $this->tenantContextService->resolveTenantContext($request, $user);
    }

    protected function resolveAuthenticatedUser(Request $request): ApiAuthResult
    {
        $authResult = $this->authenticate($request, 'access-api');
        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        return $authResult;
    }
}
