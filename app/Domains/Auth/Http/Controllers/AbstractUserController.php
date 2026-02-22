<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Access\Services\UserService;
use App\Domains\Auth\Actions\ResolveUserContextAction;
use App\Domains\Auth\Http\Controllers\Concerns\HasStrongPasswordRule;
use App\Domains\Auth\Http\Controllers\Concerns\ResolvesRoleScope;
use App\Domains\Auth\Http\Controllers\Concerns\VerifiesTotpCode;
use App\Domains\Auth\Services\MenuMetadataService;
use App\Domains\Auth\Services\TotpService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\AuditLogService;
use App\Domains\System\Services\ThemeConfigService;
use App\Domains\Tenant\Services\TenantContextService;
use Illuminate\Http\Request;

abstract class AbstractUserController extends ApiController
{
    use HasStrongPasswordRule;
    use ResolvesRoleScope;
    use VerifiesTotpCode;

    public function __construct(
        protected readonly UserService $userService,
        protected readonly TenantContextService $tenantContextService,
        protected readonly MenuMetadataService $menuMetadataService,
        protected readonly ThemeConfigService $themeConfigService,
        protected readonly AuditLogService $auditLogService,
        protected readonly TotpService $totpService,
        protected readonly ResolveUserContextAction $resolveUserContext
    ) {}

    /**
     * @return list<string>
     */
    protected function resolveRoles(User $user): array
    {
        return $this->resolveUserContext->resolveRoles($user);
    }

    /**
     * @return array{
     *   userId: string,
     *   userName: string,
     *   locale: string,
     *   preferredLocale: string,
     *   timezone: string,
     *   themeSchema: string|null,
     *   email: string,
     *   roleCode: string,
     *   roleName: string,
     *   tenantId: string,
     *   tenantName: string,
     *   twoFactorEnabled: bool,
     *   status: string,
     *   version: string,
     *   createTime: string,
     *   updateTime: string
     * }
     */
    protected function resolveProfile(User $user): array
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
     * @return array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   user?: \App\Domains\Access\Models\User,
     *   actorLevel?: int,
     *   tenantId?: int|null
     * }
     */
    protected function resolveUserManagementContext(Request $request, string|array $permissionCode): array
    {
        $authResult = is_array($permissionCode)
            ? $this->authenticateAndAuthorizeAny($request, 'access-api', $permissionCode)
            : $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if (! $authResult['ok']) {
            return $authResult;
        }

        /** @var \App\Domains\Access\Models\User $authUser */
        $authUser = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($authUser);
        if ($actorLevel <= 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $tenantContext = $this->resolveTenantContext($request, $authUser);
        if (! $tenantContext['ok']) {
            return [
                'ok' => false,
                'code' => $tenantContext['code'],
                'msg' => $tenantContext['msg'],
            ];
        }

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'user' => $authUser,
            'actorLevel' => $actorLevel,
            'tenantId' => $tenantContext['tenantId'],
        ];
    }

    /**
     * @return array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   tenantId?: int|null,
     *   tenantName?: string,
     *   tenants?: list<array{tenantId: string, tenantName: string}>
     * }
     */
    protected function resolveTenantContext(Request $request, User $user): array
    {
        return $this->tenantContextService->resolveTenantContext($request, $user);
    }
}
