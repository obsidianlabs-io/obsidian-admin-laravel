<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Actions\ListRolesQueryAction;
use App\Domains\Access\Data\RoleResponseData;
use App\Domains\Access\Data\RoleSnapshot;
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
use App\DTOs\Role\SyncRolePermissionsDTO;
use App\DTOs\Role\UpdateRoleDTO;
use App\Http\Requests\Api\Role\ListRolesRequest;
use App\Http\Requests\Api\Role\StoreRoleRequest;
use App\Http\Requests\Api\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Api\Role\UpdateRoleRequest;
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

    public function list(ListRolesRequest $request, ListRolesQueryAction $listRolesQuery): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.view')
        );
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $input = $request->toDTO();
        $actorLevel = $context->actorLevel();

        $query = $listRolesQuery->handle(
            $input,
            $actorLevel,
            $context->tenantId(),
            $context->isSuper(),
        );

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );

            $request->attributes->set('actorRoleLevel', $actorLevel);
            $records = RoleListResource::collection($page['records'])->resolve($request);

            return $this->success(
                $this->cursorPaginationPayload($page, $records, ['actorLevel' => $actorLevel])->toArray()
            );
        }

        $total = (clone $query)->count();

        $request->attributes->set('actorRoleLevel', $actorLevel);
        $records = RoleListResource::collection(
            $query->orderBy('id')
                ->forPage($input->current, $input->size)
                ->get()
        )->resolve($request);

        return $this->success(
            $this->offsetPaginationPayload(
                $input->current,
                $input->size,
                $total,
                $records,
                ['actorLevel' => $actorLevel],
            )->toArray()
        );
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
                    ->active()
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

        $input = $request->toDTO();
        $reservedRoleCodeError = $this->validateReservedRoleCode($input->roleCode);
        if ($reservedRoleCodeError instanceof JsonResponse) {
            return $reservedRoleCodeError;
        }
        $roleUniquenessError = $this->validateRoleUniqueness(
            roleCode: $input->roleCode,
            roleName: $input->roleName,
            tenantId: $targetTenantId
        );
        if ($roleUniquenessError instanceof JsonResponse) {
            return $roleUniquenessError;
        }
        $requestedRoleLevel = $input->level;
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError instanceof JsonResponse) {
            return $roleLevelError;
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $input->permissionCodes,
            $targetTenantId,
            $isSuper
        );
        if ($permissionResolution->failed()) {
            return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        return $this->withIdempotency($request, $user, function () use ($input, $targetTenantId, $permissionIds, $user, $request): JsonResponse {
            $role = $this->roleService->create($input->toCreateRoleDTO($targetTenantId), $permissionIds);

            $this->auditLogService->record(
                action: 'role.create',
                auditable: $role,
                actor: $user,
                request: $request,
                newValues: RoleSnapshot::forCreateAudit($role, $targetTenantId, count($permissionIds))->toArray(),
                tenantId: $targetTenantId
            );

            return $this->success(RoleResponseData::fromModel($role)->toArray(), 'Role created');
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

        $input = $request->toDTO();
        $reservedRoleCodeError = $this->validateReservedRoleCode($input->roleCode, $role);
        if ($reservedRoleCodeError instanceof JsonResponse) {
            return $reservedRoleCodeError;
        }
        $roleUniquenessError = $this->validateRoleUniqueness(
            roleCode: $input->roleCode,
            roleName: $input->roleName,
            tenantId: $targetTenantId,
            ignoreRoleId: (int) $role->id
        );
        if ($roleUniquenessError instanceof JsonResponse) {
            return $roleUniquenessError;
        }
        $requestedRoleLevel = $input->level;
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError instanceof JsonResponse) {
            return $roleLevelError;
        }
        $permissionIds = null;
        if ($input->hasPermissionCodes) {
            $permissionResolution = $this->resolveAssignablePermissions(
                $input->permissionCodes,
                $targetTenantId,
                $context->isSuper()
            );
            if ($permissionResolution->failed()) {
                return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
            }
            $permissionIds = $permissionResolution->permissionIds();
        }

        $oldValues = RoleSnapshot::forUpdateAudit($role)->toArray();

        $this->roleService->update(
            $role,
            $input->toUpdateRoleDTO((string) $role->status),
            $permissionIds
        );

        $this->auditLogService->record(
            action: 'role.update',
            auditable: $role,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: RoleSnapshot::forUpdateAudit($role)->toArray(),
            tenantId: $targetTenantId
        );

        return $this->success(RoleResponseData::fromModel($role, $request)->toArray(), 'Role updated');
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

        $oldValues = RoleSnapshot::forStatusAudit($role)->toArray();

        if ((string) $role->status === '1') {
            $this->roleService->update($role, new UpdateRoleDTO(
                code: (string) $role->code,
                name: (string) $role->name,
                description: (string) ($role->description ?? ''),
                status: '2',
                level: (int) ($role->level ?? self::DEFAULT_ROLE_LEVEL),
            ));

            $this->auditLogService->record(
                action: 'role.deactivate',
                auditable: $role,
                actor: $user,
                request: $request,
                oldValues: $oldValues,
                newValues: RoleSnapshot::forStatusAudit($role)->toArray(),
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

        $input = $request->toDTO();
        $permissionResolution = $this->resolveAssignablePermissions(
            $input->permissionCodes,
            $role->tenant_id !== null ? (int) $role->tenant_id : null,
            $context->isSuper()
        );
        if ($permissionResolution->failed()) {
            return $this->error(self::FORBIDDEN_CODE, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        $this->roleService->syncPermissions($role, new SyncRolePermissionsDTO($permissionIds));
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
