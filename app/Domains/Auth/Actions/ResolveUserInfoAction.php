<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Actions\Results\ResolvedUserInfo;
use App\Domains\Auth\Services\MenuMetadataService;
use App\Domains\Shared\Auth\TenantContext;
use App\Domains\System\Services\ThemeConfigService;

final class ResolveUserInfoAction
{
    public function __construct(
        private readonly ResolveUserContextAction $resolveUserContext,
        private readonly ThemeConfigService $themeConfigService,
        private readonly MenuMetadataService $menuMetadataService,
    ) {}

    public function handle(User $user, TenantContext $tenantContext): ResolvedUserInfo
    {
        $user->loadMissing('role.permissions', 'tenant', 'preference');

        $profile = $this->resolveUserContext->resolveProfile($user);
        $roles = $this->resolveUserContext->resolveRoles($user);
        $permissionCodes = $user->permissionCodes();
        $navigation = $this->menuMetadataService->resolveForUser(
            user: $user,
            tenantId: $tenantContext->tenantId(),
            roleCodes: $roles->codes(),
            permissionCodes: $permissionCodes,
        );
        $themeConfig = $this->themeConfigService->resolveEffectiveConfig(null, $profile->themeSchema);

        return new ResolvedUserInfo(
            profile: $profile,
            themeConfig: $themeConfig->config,
            themeProfileVersion: $themeConfig->profileVersion,
            roles: $roles,
            buttons: $permissionCodes,
            currentTenantId: (string) ($tenantContext->tenantId() ?? ''),
            currentTenantName: $tenantContext->tenantName(),
            tenants: $tenantContext->tenants(),
            navigation: $navigation,
        );
    }
}
