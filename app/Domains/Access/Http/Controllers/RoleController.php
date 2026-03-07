<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\RoleListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\Domains\Access\Services\RoleService;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Auth\AssignablePermissionIdsResult;
use App\Domains\Shared\Auth\ManagementContext;
use App\Domains\Shared\Auth\RoleScopeContext;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Role\ListRolesRequest;
use App\Http\Requests\Api\Role\StoreRoleRequest;
use App\Http\Requests\Api\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Api\Role\UpdateRoleRequest;
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    private const ROLE_LEVEL_MIN = 1;

    private const ROLE_LEVEL_MAX = 999;

    private const DEFAULT_ROLE_LEVEL = 100;

    public function __construct(
        private readonly RoleService $roleService,
        private readonly RoleScopeGuardService $roleScopeGuardService,
        private readonly AuditLogService $auditLogService,
        private readonly TenantContextService $tenantContextService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function assignablePermissions(Request $request): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticate($request, 'access-api')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $user = $context->requireUser();
        if (! ($user->hasPermission('role.view') || $user->hasPermission('role.manage'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $scopeTenantId = $context->tenantId();
        $isSuper = $context->isSuper();

        $records = $this->apiCacheService->remember(
            'permissions',
            'assignable|tenant:'.($scopeTenantId ?? 0).'|super:'.($isSuper ? 1 : 0),
            function () use ($scopeTenantId, $isSuper): array {
                return $this->roleScopeGuardService->buildAssignablePermissionQuery($scopeTenantId, $isSuper)
                    ->orderBy('group')
                    ->orderBy('id')
                    ->get(['id', 'code', 'name', 'group'])
                    ->map(static function (Permission $permission): array {
                        return [
                            'id' => $permission->id,
                            'permissionCode' => $permission->code,
                            'permissionName' => $permission->name,
                            'group' => (string) ($permission->group ?? ''),
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return $this->success([
            'records' => $records,
        ]);
    }

    public function list(ListRolesRequest $request): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.view')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $level = isset($validated['level']) ? (int) $validated['level'] : null;
        $actorLevel = $context->actorLevel();

        $query = Role::query()
            ->with('tenant:id,name')
            ->with('permissions:id,code,status')
            ->withCount('users')
            ->select(['id', 'code', 'name', 'description', 'status', 'tenant_id', 'level', 'created_at', 'updated_at'])
            ->where('level', '<=', $actorLevel);
        $this->roleScopeGuardService->applyRoleVisibilityScope($query, $context->tenantId(), $context->isSuper());
        $this->applyRoleFilters($query, $keyword, $status, $level);

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                false
            );

            $request->attributes->set('actorRoleLevel', $actorLevel);
            $records = RoleListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'actorLevel' => $actorLevel,
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();

        $request->attributes->set('actorRoleLevel', $actorLevel);
        $records = RoleListResource::collection(
            $query->orderBy('id')
                ->forPage($current, $size)
                ->get()
        )->resolve($request);

        return $this->success([
            'actorLevel' => $actorLevel,
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'records' => $records,
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticate($request, 'access-api')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $user = $context->requireUser();
        if (! ($user->hasPermission('role.view') || $user->hasPermission('user.manage') || $user->hasPermission('user.view'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $scopeTenantId = $context->tenantId();
        $isSuper = $context->isSuper();
        $actorLevel = $context->actorLevel();
        $manageableOnly = filter_var($request->query('manageableOnly', false), FILTER_VALIDATE_BOOLEAN);
        $records = $this->apiCacheService->remember(
            'roles',
            'all|tenant:'.($scopeTenantId ?? 0).'|level:'.$actorLevel.'|super:'.($isSuper ? 1 : 0).'|manageable:'.($manageableOnly ? 1 : 0),
            function () use ($actorLevel, $scopeTenantId, $isSuper, $manageableOnly): array {
                $query = Role::query()
                    ->where('status', '1')
                    ->orderBy('id');
                $this->roleScopeGuardService->applyRoleVisibilityScope($query, $scopeTenantId, $isSuper);
                $this->roleScopeGuardService->applyRoleManageableFilter(
                    $query,
                    $actorLevel,
                    $scopeTenantId,
                    $isSuper,
                    $manageableOnly
                );

                return $query
                    ->get(['id', 'code', 'name', 'level'])
                    ->map(function (Role $role) use ($actorLevel, $scopeTenantId, $isSuper): array {
                        $roleLevel = max(0, (int) ($role->level ?? 0));
                        $manageable = $this->roleScopeGuardService->isRoleManageableInScope(
                            $role,
                            $actorLevel,
                            $scopeTenantId,
                            $isSuper
                        );

                        return [
                            'id' => $role->id,
                            'roleCode' => $role->code,
                            'roleName' => $role->name,
                            'level' => $roleLevel,
                            'manageable' => $manageable,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return $this->success([
            'records' => $records,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }
        $user = $context->requireUser();
        $actorLevel = $context->actorLevel();
        $targetTenantId = $context->tenantId();
        $isSuper = $context->isSuper();

        $validated = $request->validated();
        $reservedRoleCodeError = $this->validateReservedRoleCode((string) $validated['roleCode']);
        if ($reservedRoleCodeError instanceof JsonResponse) {
            return $reservedRoleCodeError;
        }
        $roleUniquenessError = $this->validateRoleUniqueness(
            roleCode: (string) $validated['roleCode'],
            roleName: (string) $validated['roleName'],
            tenantId: $targetTenantId
        );
        if ($roleUniquenessError instanceof JsonResponse) {
            return $roleUniquenessError;
        }
        $requestedRoleLevel = (int) ($validated['level'] ?? self::DEFAULT_ROLE_LEVEL);
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError instanceof JsonResponse) {
            return $roleLevelError;
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $this->normalizePermissionCodes($validated['permissionCodes'] ?? []),
            $targetTenantId,
            $isSuper
        );
        if ($permissionResolution->failed()) {
            return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        return $this->withIdempotency($request, $user, function () use ($validated, $targetTenantId, $permissionIds, $requestedRoleLevel, $user, $request): JsonResponse {
            $role = $this->roleService->create([
                'code' => (string) $validated['roleCode'],
                'name' => (string) $validated['roleName'],
                'description' => (string) ($validated['description'] ?? ''),
                'status' => (string) ($validated['status'] ?? '1'),
                'tenant_id' => $targetTenantId,
                'level' => $requestedRoleLevel,
            ], $permissionIds);

            $this->auditLogService->record(
                action: 'role.create',
                auditable: $role,
                actor: $user,
                request: $request,
                newValues: [
                    'roleCode' => $role->code,
                    'roleName' => $role->name,
                    'tenantId' => $targetTenantId,
                    'level' => (int) $role->level,
                    'permissionCount' => count($permissionIds),
                ],
                tenantId: $targetTenantId
            );

            return $this->success($this->roleResponse($role), 'Role created');
        });
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }
        $user = $context->requireUser();
        $actorLevel = $context->actorLevel();
        $role = $this->resolveScopedRole($id, $context->tenantId(), $context->isSuper());
        if (! $role instanceof Role) {
            return $this->roleScopeErrorResponse($id);
        }
        if (! $this->roleScopeGuardService->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $role, 'Role');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $targetTenantId = $role->tenant_id !== null ? (int) $role->tenant_id : null;

        $validated = $request->validated();
        $reservedRoleCodeError = $this->validateReservedRoleCode((string) $validated['roleCode'], $role);
        if ($reservedRoleCodeError instanceof JsonResponse) {
            return $reservedRoleCodeError;
        }
        $roleUniquenessError = $this->validateRoleUniqueness(
            roleCode: (string) $validated['roleCode'],
            roleName: (string) $validated['roleName'],
            tenantId: $targetTenantId,
            ignoreRoleId: (int) $role->id
        );
        if ($roleUniquenessError instanceof JsonResponse) {
            return $roleUniquenessError;
        }
        $requestedRoleLevel = (int) ($validated['level'] ?? $role->level ?? self::DEFAULT_ROLE_LEVEL);
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError instanceof JsonResponse) {
            return $roleLevelError;
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $this->normalizePermissionCodes($validated['permissionCodes'] ?? []),
            $targetTenantId,
            $context->isSuper()
        );
        if ($permissionResolution->failed()) {
            return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        $oldValues = [
            'roleCode' => $role->code,
            'roleName' => $role->name,
            'description' => (string) ($role->description ?? ''),
            'status' => (string) $role->status,
            'level' => (int) ($role->level ?? 0),
        ];

        $this->roleService->update($role, [
            'code' => (string) $validated['roleCode'],
            'name' => (string) $validated['roleName'],
            'description' => (string) ($validated['description'] ?? ''),
            'status' => (string) ($validated['status'] ?? $role->status),
            'level' => $requestedRoleLevel,
        ], array_key_exists('permissionCodes', $validated) ? $permissionIds : null);

        $this->auditLogService->record(
            action: 'role.update',
            auditable: $role,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'roleCode' => $role->code,
                'roleName' => $role->name,
                'description' => (string) ($role->description ?? ''),
                'status' => (string) $role->status,
                'level' => (int) ($role->level ?? 0),
            ],
            tenantId: $targetTenantId
        );

        return $this->success($this->roleResponse($role, $request), 'Role updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }
        $user = $context->requireUser();
        $actorLevel = $context->actorLevel();
        $role = $this->resolveScopedRole($id, $context->tenantId(), $context->isSuper(), true);
        if (! $role instanceof Role) {
            return $this->roleScopeErrorResponse($id);
        }
        if (! $this->roleScopeGuardService->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        if ($this->roleScopeGuardService->isReservedRoleCode((string) $role->code)) {
            return $this->error(self::FORBIDDEN_CODE, 'Super role cannot be deleted');
        }

        $oldValues = [
            'roleCode' => $role->code,
            'roleName' => $role->name,
            'tenantId' => $role->tenant_id ? (int) $role->tenant_id : null,
            'status' => (string) $role->status,
        ];

        if ((string) $role->status === '1') {
            $this->roleService->update($role, [
                'code' => (string) $role->code,
                'name' => (string) $role->name,
                'description' => (string) ($role->description ?? ''),
                'status' => '2',
                'level' => (int) ($role->level ?? self::DEFAULT_ROLE_LEVEL),
            ]);

            $this->auditLogService->record(
                action: 'role.deactivate',
                auditable: $role,
                actor: $user,
                request: $request,
                oldValues: $oldValues,
                newValues: [
                    'roleCode' => (string) $role->code,
                    'roleName' => (string) $role->name,
                    'tenantId' => $role->tenant_id ? (int) $role->tenant_id : null,
                    'status' => (string) $role->status,
                ],
                tenantId: $role->tenant_id ? (int) $role->tenant_id : null
            );

            return $this->deletionActionSuccess('role', (int) $role->id, 'deactivated', 'Role deactivated');
        }

        $assignedUsers = (int) ($role->users_count ?? 0);
        if ($assignedUsers > 0) {
            return $this->deleteConflict(
                resource: 'role',
                resourceId: (int) $role->id,
                dependencies: ['users' => $assignedUsers],
                suggestedAction: 'reassign_users_then_retry'
            );
        }

        $this->roleService->delete($role);
        $this->auditLogService->record(
            action: 'role.soft_delete',
            auditable: $role,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            tenantId: $role->tenant_id ? (int) $role->tenant_id : null
        );

        return $this->deletionActionSuccess('role', (int) $id, 'soft_deleted', 'Role deleted');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }
        $user = $context->requireUser();
        $actorLevel = $context->actorLevel();
        $role = $this->resolveScopedRole($id, $context->tenantId(), $context->isSuper());
        if (! $role instanceof Role) {
            return $this->roleScopeErrorResponse($id);
        }
        if (! $this->roleScopeGuardService->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $role, 'Role');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $permissionResolution = $this->resolveAssignablePermissions(
            $this->normalizePermissionCodes($request->validated()['permissionCodes']),
            $role->tenant_id !== null ? (int) $role->tenant_id : null,
            $context->isSuper()
        );
        if ($permissionResolution->failed()) {
            return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        $this->roleService->syncPermissions($role, $permissionIds);
        $this->auditLogService->record(
            action: 'role.sync_permissions',
            auditable: $role,
            actor: $user,
            request: $request,
            newValues: [
                'permissionCount' => count($permissionIds),
            ],
            tenantId: $role->tenant_id ? (int) $role->tenant_id : null
        );

        return $this->success([], 'Role permissions updated');
    }

    private function resolveRoleConsoleContext(Request $request, ApiAuthResult $authResult): ManagementContext
    {
        if ($authResult->failed()) {
            return ManagementContext::failure($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ManagementContext::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $actorLevel = $this->roleScopeGuardService->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return ManagementContext::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $roleScope = $this->resolveRoleScope($request, $user);
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

    /**
     * @param  Builder<Role>  $query
     */
    private function applyRoleFilters(Builder $query, string $keyword, string $status, ?int $level): void
    {
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('code', 'like', '%'.$keyword.'%')
                    ->orWhere('name', 'like', '%'.$keyword.'%');
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($level !== null && $level > 0) {
            $query->where('level', $level);
        }
    }

    /**
     * @param  array<int, mixed>  $permissionCodes
     * @return list<string>
     */
    private function normalizePermissionCodes(array $permissionCodes): array
    {
        return array_values(array_map(static fn (mixed $code): string => (string) $code, $permissionCodes));
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    private function resolveAssignablePermissions(
        array $permissionCodes,
        ?int $tenantId,
        bool $isSuper
    ): AssignablePermissionIdsResult {
        return $this->roleScopeGuardService->resolveAssignablePermissionIds(
            $permissionCodes,
            $tenantId,
            $isSuper
        );
    }

    private function resolveScopedRole(int $id, ?int $tenantId, bool $isSuper, bool $withUserCount = false): ?Role
    {
        return $this->roleScopeGuardService->findRoleInScope($id, $tenantId, $isSuper, $withUserCount);
    }

    private function roleScopeErrorResponse(int $id): JsonResponse
    {
        return Role::query()->whereKey($id)->exists()
            ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
            : $this->error(self::PARAM_ERROR_CODE, 'Role not found');
    }

    /**
     * @return array{
     *   id: int,
     *   roleCode: string,
     *   roleName: string,
     *   level: int,
     *   version?: string,
     *   updateTime?: string
     * }
     */
    private function roleResponse(Role $role, ?Request $request = null): array
    {
        $response = [
            'id' => $role->id,
            'roleCode' => (string) $role->code,
            'roleName' => (string) $role->name,
            'level' => (int) ($role->level ?? 0),
        ];

        if ($request instanceof Request) {
            $response['version'] = (string) ($role->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0);
            $response['updateTime'] = ApiDateTime::formatForRequest($role->updated_at, $request);
        }

        return $response;
    }

    private function resolveRoleScope(Request $request, User $user): RoleScopeContext
    {
        return $this->tenantContextService->resolveRoleScope($request, $user);
    }

    private function validateReservedRoleCode(string $roleCode, ?Role $existingRole = null): ?JsonResponse
    {
        if ($this->roleScopeGuardService->isRoleCodeChangeAllowed($roleCode, $existingRole)) {
            return null;
        }

        return $this->error(self::PARAM_ERROR_CODE, 'Role code is reserved');
    }

    private function validateRoleUniqueness(
        string $roleCode,
        string $roleName,
        ?int $tenantId,
        ?int $ignoreRoleId = null
    ): ?JsonResponse {
        if ($this->roleScopeGuardService->roleCodeExistsInScope($roleCode, $tenantId, $ignoreRoleId)) {
            return $this->error(self::PARAM_ERROR_CODE, __('validation.unique', ['attribute' => 'role code']));
        }

        if ($this->roleScopeGuardService->roleNameExistsInScope($roleName, $tenantId, $ignoreRoleId)) {
            return $this->error(self::PARAM_ERROR_CODE, __('validation.unique', ['attribute' => 'role name']));
        }

        return null;
    }

    private function validateRequestedRoleLevel(int $requestedLevel, int $actorLevel): ?JsonResponse
    {
        if ($requestedLevel < self::ROLE_LEVEL_MIN || $requestedLevel > self::ROLE_LEVEL_MAX) {
            return $this->error(
                self::PARAM_ERROR_CODE,
                'Role level must be between '.self::ROLE_LEVEL_MIN.' and '.self::ROLE_LEVEL_MAX
            );
        }

        if (! $this->roleScopeGuardService->isRequestedRoleLevelAllowed(
            $requestedLevel,
            $actorLevel,
            self::ROLE_LEVEL_MIN,
            self::ROLE_LEVEL_MAX
        )) {
            return $this->error(self::FORBIDDEN_CODE, 'Role level must be lower than your current role level');
        }

        return null;
    }
}
