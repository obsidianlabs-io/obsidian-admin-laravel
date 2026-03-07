<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Data\ThemeActorScopeData;
use App\Domains\System\Data\ThemeConfigResponseData;
use App\Domains\System\Models\ThemeProfile;
use App\Domains\System\Services\AuditLogService;
use App\Domains\System\Services\ThemeConfigService;
use App\Http\Requests\Api\Theme\UpdateThemeConfigRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeConfigController extends ApiController
{
    public function __construct(
        private readonly ThemeConfigService $themeConfigService,
        private readonly AuditLogService $auditLogService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorizeAny($request, 'access-api', ['theme.view', 'theme.manage']);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $accessError = $this->resolveThemeConfigAccessError($request, $user);
        if ($accessError instanceof JsonResponse) {
            return $accessError;
        }

        $scope = $this->resolveScope($user);
        $scopeConfig = $this->themeConfigService->describeScopeConfig(
            $scope->scopeType,
            $scope->scopeId,
            $scope->scopeName
        );
        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success((new ThemeConfigResponseData($scopeConfig, $effective, true))->toArray());
    }

    public function publicShow(Request $request): JsonResponse
    {
        unset($request);

        $scopeConfig = $this->themeConfigService->describeScopeConfig(
            ThemeProfile::SCOPE_PLATFORM,
            null,
            'Project Default'
        );
        $effective = $this->themeConfigService->resolveEffectiveConfig(null, null);

        return $this->success((new ThemeConfigResponseData($scopeConfig, $effective, false))->toArray());
    }

    public function update(UpdateThemeConfigRequest $request): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'theme.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $accessError = $this->resolveThemeConfigAccessError($request, $user);
        if ($accessError instanceof JsonResponse) {
            return $accessError;
        }

        $scope = $this->resolveScope($user);
        $input = $request->toDTO();
        if (! $input->hasChanges()) {
            return $this->error(self::PARAM_ERROR_CODE, 'No theme fields to update');
        }

        $before = $this->themeConfigService->describeScopeConfig(
            $scope->scopeType,
            $scope->scopeId,
            $scope->scopeName
        );

        $updated = $this->themeConfigService->updateScopeConfig(
            $scope->scopeType,
            $scope->scopeId,
            $scope->scopeName,
            $input,
            (int) $user->id
        );

        $this->auditLogService->record(
            action: 'theme.config.update',
            auditable: 'theme-profile',
            actor: $user,
            request: $request,
            oldValues: $before->toAuditArray(),
            newValues: $updated->toAuditArray(),
            tenantId: $updated->scopeType === 'tenant' ? $updated->scopeId : null
        );

        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success(
            (new ThemeConfigResponseData($updated, $effective, true))->toArray(),
            'Theme configuration updated'
        );
    }

    public function reset(Request $request): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'theme.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $accessError = $this->resolveThemeConfigAccessError($request, $user);
        if ($accessError instanceof JsonResponse) {
            return $accessError;
        }

        $scope = $this->resolveScope($user);
        $before = $this->themeConfigService->describeScopeConfig(
            $scope->scopeType,
            $scope->scopeId,
            $scope->scopeName
        );

        $updated = $this->themeConfigService->resetScopeConfig(
            $scope->scopeType,
            $scope->scopeId,
            $scope->scopeName,
            (int) $user->id
        );

        $this->auditLogService->record(
            action: 'theme.config.reset',
            auditable: 'theme-profile',
            actor: $user,
            request: $request,
            oldValues: $before->toAuditArray(),
            newValues: $updated->toAuditArray(),
            tenantId: $updated->scopeType === 'tenant' ? $updated->scopeId : null
        );

        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success(
            (new ThemeConfigResponseData($updated, $effective, true))->toArray(),
            'Theme configuration reset'
        );
    }

    private function resolveScope(User $user): ThemeActorScopeData
    {
        return $this->themeConfigService->resolveActorScope($user, null);
    }

    private function resolveThemeConfigAccessError(Request $request, User $user): ?JsonResponse
    {
        if (! $this->isSuperAdmin($user)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $selectedTenantHeader = $request->header('X-Tenant-Id');
        $selectedTenantId = is_numeric($selectedTenantHeader) ? (int) $selectedTenantHeader : 0;
        if ($selectedTenantId > 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Switch to No Tenant to manage theme configuration');
        }

        return null;
    }

    private function resolveUserThemeSchema(User $user): ?string
    {
        $preference = $user->preference;

        if (! $preference instanceof UserPreference) {
            return null;
        }

        $themeSchema = trim((string) $preference->theme_schema);

        return $themeSchema !== '' ? $themeSchema : null;
    }
}
