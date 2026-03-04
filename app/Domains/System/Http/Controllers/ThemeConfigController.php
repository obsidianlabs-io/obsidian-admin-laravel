<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\Shared\Http\Controllers\ApiController;
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
        if ($accessError !== null) {
            return $this->error($accessError['code'], $accessError['msg']);
        }

        $scope = $this->resolveScope($user);
        $scopeConfig = $this->themeConfigService->describeScopeConfig(
            $scope['scopeType'],
            $scope['scopeId'],
            $scope['scopeName']
        );
        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success([
            'scopeType' => $scopeConfig['scopeType'],
            'scopeId' => $scopeConfig['scopeId'] ? (string) $scopeConfig['scopeId'] : '',
            'scopeName' => $scopeConfig['scopeName'],
            'version' => $scopeConfig['version'],
            'config' => $scopeConfig['config'],
            'effectiveConfig' => $effective['config'],
            'effectiveVersion' => (int) $effective['profileVersion'],
            'editable' => true,
        ]);
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

        return $this->success([
            'scopeType' => $scopeConfig['scopeType'],
            'scopeId' => '',
            'scopeName' => $scopeConfig['scopeName'],
            'version' => $scopeConfig['version'],
            'config' => $effective['config'],
            'effectiveConfig' => $effective['config'],
            'effectiveVersion' => (int) $effective['profileVersion'],
            'editable' => false,
        ]);
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
        if ($accessError !== null) {
            return $this->error($accessError['code'], $accessError['msg']);
        }

        $scope = $this->resolveScope($user);
        $validated = $request->validated();
        if ($validated === []) {
            return $this->error(self::PARAM_ERROR_CODE, 'No theme fields to update');
        }

        $before = $this->themeConfigService->describeScopeConfig(
            $scope['scopeType'],
            $scope['scopeId'],
            $scope['scopeName']
        );

        $updated = $this->themeConfigService->updateScopeConfig(
            $scope['scopeType'],
            $scope['scopeId'],
            $scope['scopeName'],
            $validated,
            (int) $user->id
        );

        $this->auditLogService->record(
            action: 'theme.config.update',
            auditable: 'theme-profile',
            actor: $user,
            request: $request,
            oldValues: [
                'scopeType' => $before['scopeType'],
                'scopeId' => $before['scopeId'],
                'version' => $before['version'],
                'config' => $before['config'],
            ],
            newValues: [
                'scopeType' => $updated['scopeType'],
                'scopeId' => $updated['scopeId'],
                'version' => $updated['version'],
                'config' => $updated['config'],
            ],
            tenantId: $updated['scopeType'] === 'tenant' ? $updated['scopeId'] : null
        );

        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success([
            'scopeType' => $updated['scopeType'],
            'scopeId' => $updated['scopeId'] ? (string) $updated['scopeId'] : '',
            'scopeName' => $updated['scopeName'],
            'version' => $updated['version'],
            'config' => $updated['config'],
            'effectiveConfig' => $effective['config'],
            'effectiveVersion' => (int) $effective['profileVersion'],
        ], 'Theme configuration updated');
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
        if ($accessError !== null) {
            return $this->error($accessError['code'], $accessError['msg']);
        }

        $scope = $this->resolveScope($user);
        $before = $this->themeConfigService->describeScopeConfig(
            $scope['scopeType'],
            $scope['scopeId'],
            $scope['scopeName']
        );

        $updated = $this->themeConfigService->resetScopeConfig(
            $scope['scopeType'],
            $scope['scopeId'],
            $scope['scopeName'],
            (int) $user->id
        );

        $this->auditLogService->record(
            action: 'theme.config.reset',
            auditable: 'theme-profile',
            actor: $user,
            request: $request,
            oldValues: [
                'scopeType' => $before['scopeType'],
                'scopeId' => $before['scopeId'],
                'version' => $before['version'],
                'config' => $before['config'],
            ],
            newValues: [
                'scopeType' => $updated['scopeType'],
                'scopeId' => $updated['scopeId'],
                'version' => $updated['version'],
                'config' => $updated['config'],
            ],
            tenantId: $updated['scopeType'] === 'tenant' ? $updated['scopeId'] : null
        );

        $effective = $this->themeConfigService->resolveEffectiveConfig(
            null,
            $this->resolveUserThemeSchema($user)
        );

        return $this->success([
            'scopeType' => $updated['scopeType'],
            'scopeId' => $updated['scopeId'] ? (string) $updated['scopeId'] : '',
            'scopeName' => $updated['scopeName'],
            'version' => $updated['version'],
            'config' => $updated['config'],
            'effectiveConfig' => $effective['config'],
            'effectiveVersion' => (int) $effective['profileVersion'],
        ], 'Theme configuration reset');
    }

    /**
     * @return array{scopeType: 'platform', scopeId: null, scopeName: string}
     */
    private function resolveScope(User $user): array
    {
        return $this->themeConfigService->resolveActorScope($user, null);
    }

    /**
     * @return array{code: string, msg: string}|null
     */
    private function resolveThemeConfigAccessError(Request $request, User $user): ?array
    {
        if (! $this->isSuperAdmin($user)) {
            return [
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantHeader = $request->header('X-Tenant-Id');
        $selectedTenantId = is_numeric($selectedTenantHeader) ? (int) $selectedTenantHeader : 0;
        if ($selectedTenantId > 0) {
            return [
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Switch to No Tenant to manage theme configuration',
            ];
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
