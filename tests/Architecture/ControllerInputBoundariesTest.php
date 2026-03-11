<?php

declare(strict_types=1);

use App\Domains\Access\Actions\ListPermissionsQueryAction;
use App\Domains\Access\Actions\ListRolesQueryAction;
use App\Domains\Access\Actions\ListUsersQueryAction;
use App\Domains\Access\Http\Controllers\PermissionController;
use App\Domains\Access\Http\Controllers\RoleController;
use App\Domains\Access\Http\Controllers\UserManagementController;
use App\Domains\Access\Services\PermissionService;
use App\Domains\Access\Services\RoleService;
use App\Domains\Auth\Actions\ResolveUserContextAction;
use App\Domains\Auth\Actions\ResolveUserInfoAction;
use App\Domains\Auth\Actions\Results\ResolvedUserInfo;
use App\Domains\Auth\Actions\Results\ResolvedUserProfile;
use App\Domains\Auth\Actions\Results\ResolvedUserRoles;
use App\Domains\Auth\Actions\Results\UpdateOwnProfileResult;
use App\Domains\Auth\Actions\Results\UserProfileSnapshot;
use App\Domains\Auth\Services\MenuMetadataService;
use App\Domains\Auth\Services\Results\ResolvedUserNavigation;
use App\Domains\Auth\Services\Results\SessionRecordsResult;
use App\Domains\Auth\Services\SessionProjector;
use App\Domains\Shared\Services\IdempotencyService;
use App\Domains\Shared\Services\Results\IdempotencyBeginResult;
use App\Domains\System\Actions\FeatureFlag\ListFeatureFlagsAction;
use App\Domains\System\Actions\ListAuditLogsQueryAction;
use App\Domains\System\Actions\ListLanguageTranslationsQueryAction;
use App\Domains\System\Data\ApiAccessLogPruneResultData;
use App\Domains\System\Data\AuditLogPruneResultData;
use App\Domains\System\Data\AuditPolicyGlobalUpdateResultData;
use App\Domains\System\Data\AuditPolicyHistoryPageData;
use App\Domains\System\Data\AuditPolicyRecordsData;
use App\Domains\System\Data\AuditPolicyUpdateResultData;
use App\Domains\System\Data\CrudSchemaData;
use App\Domains\System\Data\EffectiveThemeConfigData;
use App\Domains\System\Data\FeatureFlagListPageData;
use App\Domains\System\Data\FeatureFlagScopeData;
use App\Domains\System\Data\HealthSnapshotData;
use App\Domains\System\Data\ThemeActorScopeData;
use App\Domains\System\Data\ThemeScopeConfigData;
use App\Domains\System\Http\Controllers\AuditLogController;
use App\Domains\System\Http\Controllers\LanguageController;
use App\Domains\System\Services\ApiAccessLogService;
use App\Domains\System\Services\AuditPolicyService;
use App\Domains\System\Services\CrudSchemaService;
use App\Domains\System\Services\FeatureFlagService;
use App\Domains\System\Services\HealthStatusService;
use App\Domains\System\Services\LanguageService;
use App\Domains\System\Services\ThemeConfigService;
use App\Domains\Tenant\Actions\ListOrganizationsQueryAction;
use App\Domains\Tenant\Actions\ListTeamsQueryAction;
use App\Domains\Tenant\Actions\ListTenantsQueryAction;
use App\Domains\Tenant\Http\Controllers\OrganizationController;
use App\Domains\Tenant\Http\Controllers\TeamController;
use App\Domains\Tenant\Http\Controllers\TenantController;
use App\DTOs\Audit\ListAuditLogsInputDTO;
use App\DTOs\Language\CreateLanguageTranslationDTO;
use App\DTOs\Language\ListLanguageTranslationsInputDTO;
use App\DTOs\Language\UpdateLanguageTranslationDTO;
use App\DTOs\Organization\ListOrganizationsInputDTO;
use App\DTOs\Permission\CreatePermissionDTO;
use App\DTOs\Permission\ListPermissionsInputDTO;
use App\DTOs\Permission\UpdatePermissionDTO;
use App\DTOs\Role\CreateRoleDTO;
use App\DTOs\Role\ListRolesInputDTO;
use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use App\DTOs\Team\ListTeamsInputDTO;
use App\DTOs\Tenant\ListTenantsInputDTO;
use App\DTOs\Theme\UpdateThemeConfigInputDTO;
use App\DTOs\User\ListUsersInputDTO;
use App\Support\OpenApiDocumentData;
use App\Support\OpenApiSpecInspector;
use Illuminate\Database\Eloquent\Builder;

test('high value controllers avoid direct validated array access', function (): void {
    $files = [
        base_path('app/Domains/Auth/Http/Controllers/AuthSessionController.php'),
        base_path('app/Domains/Auth/Http/Controllers/AuthSecurityController.php'),
        base_path('app/Domains/Auth/Http/Controllers/UserProfileController.php'),
        base_path('app/Domains/Access/Http/Controllers/UserManagementController.php'),
        base_path('app/Domains/Access/Http/Controllers/RoleController.php'),
        base_path('app/Domains/Access/Http/Controllers/PermissionController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/TenantController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/OrganizationController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/TeamController.php'),
        base_path('app/Domains/System/Http/Controllers/LanguageController.php'),
        base_path('app/Domains/System/Http/Controllers/AuditPolicyController.php'),
        base_path('app/Domains/System/Http/Controllers/AuditLogController.php'),
        base_path('app/Domains/System/Http/Controllers/FeatureFlagController.php'),
        base_path('app/Domains/System/Http/Controllers/ThemeConfigController.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect((string) $contents)
            ->not->toContain('->validated()')
            ->not->toContain('$validated[');

        if (str_contains($file, 'AuthSessionController.php')) {
            expect((string) $contents)
                ->not->toContain('AuthTokenService')
                ->not->toContain('PersonalAccessToken::findToken(')
                ->not->toContain("input('refreshToken')")
                ->not->toContain('updatedTokenCount()')
                ->not->toContain('deletedTokenCount()');
        }
    }
});

test('high value services use typed command dto inputs', function (): void {
    $signatures = [
        [RoleService::class, 'create', 0, CreateRoleDTO::class],
        [RoleService::class, 'update', 1, UpdateRoleDTO::class],
        [RoleService::class, 'syncPermissions', 1, SyncRolePermissionsDTO::class],
        [PermissionService::class, 'create', 0, CreatePermissionDTO::class],
        [PermissionService::class, 'update', 1, UpdatePermissionDTO::class],
        [LanguageService::class, 'createTranslation', 0, CreateLanguageTranslationDTO::class],
        [LanguageService::class, 'updateTranslation', 1, UpdateLanguageTranslationDTO::class],
        [ThemeConfigService::class, 'updateScopeConfig', 3, UpdateThemeConfigInputDTO::class],
    ];

    foreach ($signatures as [$class, $method, $parameterIndex, $expectedType]) {
        $reflection = new ReflectionMethod($class, $method);
        $parameter = $reflection->getParameters()[$parameterIndex];
        $type = $parameter->getType();

        expect($type)->not->toBeNull();
        expect($type?->getName())->toBe($expectedType);
    }
});

test('theme config service returns typed data objects', function (): void {
    $actorScopeReflection = new ReflectionMethod(ThemeConfigService::class, 'resolveActorScope');
    $actorScopeType = $actorScopeReflection->getReturnType();

    expect($actorScopeType)->not->toBeNull();
    expect($actorScopeType?->getName())->toBe(ThemeActorScopeData::class);

    $effectiveReflection = new ReflectionMethod(ThemeConfigService::class, 'resolveEffectiveConfig');
    $effectiveType = $effectiveReflection->getReturnType();

    expect($effectiveType)->not->toBeNull();
    expect($effectiveType?->getName())->toBe(EffectiveThemeConfigData::class);

    $scopeMethods = [
        'describeScopeConfig',
        'updateScopeConfig',
        'resetScopeConfig',
    ];

    foreach ($scopeMethods as $method) {
        $reflection = new ReflectionMethod(ThemeConfigService::class, $method);
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType?->getName())->toBe(ThemeScopeConfigData::class);
    }
});

test('audit policy service returns typed result objects', function (): void {
    $returnTypes = [
        'listEffectivePolicies' => AuditPolicyRecordsData::class,
        'listRevisionHistory' => AuditPolicyHistoryPageData::class,
        'updatePolicies' => AuditPolicyUpdateResultData::class,
        'updateGlobalPolicies' => AuditPolicyGlobalUpdateResultData::class,
        'pruneExpiredLogs' => AuditLogPruneResultData::class,
    ];

    foreach ($returnTypes as $method => $expectedType) {
        $reflection = new ReflectionMethod(AuditPolicyService::class, $method);
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType?->getName())->toBe($expectedType);
    }
});

test('system operational services return typed result objects', function (): void {
    $serviceReturnTypes = [
        [HealthStatusService::class, 'snapshot', HealthSnapshotData::class],
        [ApiAccessLogService::class, 'pruneExpiredLogs', ApiAccessLogPruneResultData::class],
        [ListFeatureFlagsAction::class, '__invoke', FeatureFlagListPageData::class],
        [FeatureFlagService::class, 'parseScopeKey', FeatureFlagScopeData::class],
    ];

    foreach ($serviceReturnTypes as [$class, $method, $expectedType]) {
        $reflection = new ReflectionMethod($class, $method);
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType?->getName())->toBe($expectedType);
    }
});

test('crud schema service returns typed schema data', function (): void {
    $reflection = new ReflectionMethod(CrudSchemaService::class, 'find');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType?->getName())->toBe(CrudSchemaData::class);
    expect($returnType?->allowsNull())->toBeTrue();
});

test('openapi inspector returns typed document data', function (): void {
    $reflection = new ReflectionMethod(OpenApiSpecInspector::class, 'inspect');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType?->getName())->toBe(OpenApiDocumentData::class);
});

test('auth session projection avoids raw session record arrays', function (): void {
    $sessionProjector = (string) file_get_contents(base_path('app/Domains/Auth/Services/SessionProjector.php'));
    $authSessionContext = (string) file_get_contents(base_path('app/Domains/Auth/Services/AuthSessionContextService.php'));
    $reflection = new ReflectionMethod(SessionProjector::class, 'listSessions');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType?->getName())->toBe(SessionRecordsResult::class);

    expect($sessionProjector)
        ->not->toContain('list<array{')
        ->not->toContain('$group[')
        ->not->toContain('$record[');

    expect($authSessionContext)
        ->not->toContain('$record[');
});

test('shared idempotency boundary returns typed result object', function (): void {
    $reflection = new ReflectionMethod(IdempotencyService::class, 'begin');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull();
    expect($returnType?->getName())->toBe(IdempotencyBeginResult::class);
});

test('user context action returns typed profile result', function (): void {
    $reflection = new ReflectionMethod(ResolveUserContextAction::class, 'resolveProfile');
    $type = $reflection->getReturnType();

    expect($type)->not->toBeNull();
    expect($type?->getName())->toBe(ResolvedUserProfile::class);
});

test('user context action returns typed roles result', function (): void {
    $reflection = new ReflectionMethod(ResolveUserContextAction::class, 'resolveRoles');
    $type = $reflection->getReturnType();

    expect($type)->not->toBeNull();
    expect($type?->getName())->toBe(ResolvedUserRoles::class);
});

test('auth menu and user info actions return typed results', function (): void {
    $menuReflection = new ReflectionMethod(MenuMetadataService::class, 'resolveForUser');
    $menuType = $menuReflection->getReturnType();

    expect($menuType)->not->toBeNull();
    expect($menuType?->getName())->toBe(ResolvedUserNavigation::class);

    $userInfoReflection = new ReflectionMethod(ResolveUserInfoAction::class, 'handle');
    $userInfoType = $userInfoReflection->getReturnType();

    expect($userInfoType)->not->toBeNull();
    expect($userInfoType?->getName())->toBe(ResolvedUserInfo::class);
});

test('update own profile result exposes typed snapshots', function (): void {
    $oldProfileReflection = new ReflectionMethod(UpdateOwnProfileResult::class, 'oldProfile');
    $oldProfileType = $oldProfileReflection->getReturnType();

    expect($oldProfileType)->not->toBeNull();
    expect($oldProfileType?->getName())->toBe(UserProfileSnapshot::class);

    $newProfileReflection = new ReflectionMethod(UpdateOwnProfileResult::class, 'newProfile');
    $newProfileType = $newProfileReflection->getReturnType();

    expect($newProfileType)->not->toBeNull();
    expect($newProfileType?->getName())->toBe(UserProfileSnapshot::class);
});

test('high value list queries are delegated to typed query actions', function (): void {
    $actionSignatures = [
        [ListUsersQueryAction::class, ListUsersInputDTO::class],
        [ListRolesQueryAction::class, ListRolesInputDTO::class],
        [ListPermissionsQueryAction::class, ListPermissionsInputDTO::class],
        [ListTenantsQueryAction::class, ListTenantsInputDTO::class],
        [ListOrganizationsQueryAction::class, ListOrganizationsInputDTO::class],
        [ListTeamsQueryAction::class, ListTeamsInputDTO::class],
        [ListLanguageTranslationsQueryAction::class, ListLanguageTranslationsInputDTO::class],
        [ListAuditLogsQueryAction::class, ListAuditLogsInputDTO::class],
    ];

    foreach ($actionSignatures as [$actionClass, $dtoClass]) {
        $reflection = new ReflectionMethod($actionClass, 'handle');
        $returnType = $reflection->getReturnType();

        expect($returnType)->not->toBeNull();
        expect($returnType?->getName())->toBe(Builder::class);

        $parameterTypes = array_map(
            static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
            $reflection->getParameters(),
        );

        expect($parameterTypes)->toContain($dtoClass);
    }

    $userListParameters = (new ReflectionMethod(UserManagementController::class, 'listUsers'))->getParameters();
    expect($userListParameters[1]->getType()?->getName())->toBe(ListUsersQueryAction::class);

    $roleListParameters = (new ReflectionMethod(RoleController::class, 'list'))->getParameters();
    expect($roleListParameters[1]->getType()?->getName())->toBe(ListRolesQueryAction::class);

    $permissionListParameters = (new ReflectionMethod(PermissionController::class, 'list'))->getParameters();
    expect($permissionListParameters[1]->getType()?->getName())->toBe(ListPermissionsQueryAction::class);

    $tenantListParameters = (new ReflectionMethod(TenantController::class, 'list'))->getParameters();
    expect($tenantListParameters[1]->getType()?->getName())->toBe(ListTenantsQueryAction::class);

    $organizationListParameters = (new ReflectionMethod(OrganizationController::class, 'list'))->getParameters();
    expect($organizationListParameters[1]->getType()?->getName())->toBe(ListOrganizationsQueryAction::class);

    $teamListParameters = (new ReflectionMethod(TeamController::class, 'list'))->getParameters();
    expect($teamListParameters[1]->getType()?->getName())->toBe(ListTeamsQueryAction::class);

    $languageListParameters = (new ReflectionMethod(LanguageController::class, 'list'))->getParameters();
    expect($languageListParameters[1]->getType()?->getName())->toBe(ListLanguageTranslationsQueryAction::class);

    $auditLogListParameters = (new ReflectionMethod(AuditLogController::class, 'list'))->getParameters();
    expect($auditLogListParameters[1]->getType()?->getName())->toBe(ListAuditLogsQueryAction::class);
});

test('high value list controllers use shared pagination payload builders', function (): void {
    $files = [
        base_path('app/Domains/Access/Http/Controllers/UserManagementController.php'),
        base_path('app/Domains/Access/Http/Controllers/RoleController.php'),
        base_path('app/Domains/Access/Http/Controllers/PermissionController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/TenantController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/OrganizationController.php'),
        base_path('app/Domains/Tenant/Http/Controllers/TeamController.php'),
        base_path('app/Domains/System/Http/Controllers/LanguageController.php'),
        base_path('app/Domains/System/Http/Controllers/AuditLogController.php'),
    ];

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);

        expect($contents)
            ->toContain('cursorPaginationPayload(')
            ->toContain('offsetPaginationPayload(')
            ->not->toContain("'paginationMode' => 'cursor'")
            ->not->toContain("'nextCursor' => \$page['nextCursor']")
            ->not->toContain("'total' => \$total");
    }
});

test('high value mutation controllers avoid local snapshot and response helper arrays', function (): void {
    $expectations = [
        base_path('app/Domains/Tenant/Http/Controllers/TenantController.php') => [
            'private function tenantSnapshot',
            'private function tenantResponse',
        ],
        base_path('app/Domains/Tenant/Http/Controllers/OrganizationController.php') => [
            'private function organizationSnapshot',
            'private function organizationResponse',
        ],
        base_path('app/Domains/Tenant/Http/Controllers/TeamController.php') => [
            'private function teamSnapshot',
            'private function teamResponse',
        ],
        base_path('app/Domains/Access/Http/Controllers/PermissionController.php') => [
            'private function permissionSnapshot',
            'private function permissionResponse',
        ],
        base_path('app/Domains/Access/Http/Controllers/RoleController.php') => [
            'private function roleResponse',
        ],
    ];

    foreach ($expectations as $file => $fragments) {
        $contents = (string) file_get_contents($file);

        foreach ($fragments as $fragment) {
            expect($contents)->not->toContain($fragment);
        }
    }
});
