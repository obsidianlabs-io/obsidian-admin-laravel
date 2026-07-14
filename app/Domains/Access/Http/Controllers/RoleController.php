<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Actions\ListRolesQueryAction;
use App\Domains\Access\Data\RoleResponseData;
use App\Domains\Access\Data\RoleSnapshot;
use App\Domains\Access\Http\Controllers\Concerns\ResolvesRoleConsoleContext;
use App\Domains\Access\Http\Controllers\Concerns\ValidatesRoleBusinessRules;
use App\Domains\Access\Http\Resources\RoleListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\Domains\Access\Services\RoleService;
use App\Domains\Shared\Auth\AssignablePermissionIdsResult;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Services\TenantContextService;
use App\DTOs\Role\UpdateRoleInputDTO;
use App\Http\Requests\Api\Role\ListRolesRequest;
use App\Http\Requests\Api\Role\StoreRoleRequest;
use App\Http\Requests\Api\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Api\Role\UpdateRoleRequest;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    use ResolvesRoleConsoleContext;
    use ValidatesRoleBusinessRules;

    public function __construct(
        private readonly RoleService $roleService,
        private readonly RoleScopeGuardService $roleScopeGuardService,
        private readonly TenantContextService $tenantContextService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function assignablePermissions(Request $request): JsonResponse
    {
        $context = $this->resolveAuthenticatedRoleConsoleContext($request);
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $user = $context->requireUser();
        if (! ($user->hasPermission('role.view') || $user->hasPermission('role.manage'))) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
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
        $context = $this->resolveAuthorizedRoleConsoleContext($request, 'role.view');
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
        $context = $this->resolveAuthenticatedRoleConsoleContext($request);
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $user = $context->requireUser();
        if (! ($user->hasPermission('role.view') || $user->hasPermission('user.manage') || $user->hasPermission('user.view'))) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
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
        $context = $this->resolveAuthorizedRoleConsoleContext($request, 'role.manage');
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
        $roleLevelError = $this->validateRequestedRoleLevel($input->level, $actorLevel);
        if ($roleLevelError instanceof JsonResponse) {
            return $roleLevelError;
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $input->permissionCodes,
            $targetTenantId,
            $isSuper
        );
        if ($permissionResolution->failed()) {
            return $this->error(ApiResultCode::FORBIDDEN, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        return $this->withIdempotency($request, $user, function () use ($input, $targetTenantId, $permissionIds, $user): JsonResponse {
            $role = $this->roleService->create(
                $input,
                $targetTenantId,
                $permissionIds,
                new AuditContext(
                    actor: $user,
                    tenantId: $targetTenantId
                )
            );

            return $this->success(RoleResponseData::fromModel($role)->toArray(), 'Role created');
        });
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveAuthorizedRoleConsoleContext($request, 'role.manage');
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
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
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
        $roleLevelError = $this->validateRequestedRoleLevel($input->level, $actorLevel);
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
                return $this->error(ApiResultCode::FORBIDDEN, $permissionResolution->message());
            }
            $permissionIds = $permissionResolution->permissionIds();
        }

        $oldValues = RoleSnapshot::forUpdateAudit($role)->toArray();

        $this->roleService->update(
            $role,
            $input,
            (string) $role->status,
            $permissionIds,
            new AuditContext(
                actor: $user,
                oldValues: $oldValues
            )
        );

        return $this->success(RoleResponseData::fromModel($role, $request)->toArray(), 'Role updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveAuthorizedRoleConsoleContext($request, 'role.manage');
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
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
        }

        if ($this->roleScopeGuardService->isReservedRoleCode((string) $role->code)) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Super role cannot be deleted');
        }

        $oldValues = RoleSnapshot::forStatusAudit($role)->toArray();

        if ((string) $role->status === '1') {
            $this->roleService->update(
                $role,
                new UpdateRoleInputDTO(
                    roleCode: (string) $role->code,
                    roleName: (string) $role->name,
                    description: (string) ($role->description ?? ''),
                    status: '2',
                    level: (int) ($role->level ?? self::DEFAULT_ROLE_LEVEL),
                    hasPermissionCodes: false,
                    permissionCodes: []
                ),
                (string) $role->status,
                null,
                new AuditContext(
                    actor: $user,
                    oldValues: $oldValues,
                    overrideAction: 'role.deactivate'
                )
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

        $this->roleService->delete(
            $role,
            new AuditContext(
                actor: $user,
                oldValues: $oldValues
            )
        );

        return $this->deletionActionSuccess('role', (int) $id, 'soft_deleted', 'Role deleted');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveAuthorizedRoleConsoleContext($request, 'role.manage');
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
            return $this->error(ApiResultCode::FORBIDDEN, 'Forbidden');
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
            return $this->error(ApiResultCode::FORBIDDEN, $permissionResolution->message());
        }
        $permissionIds = $permissionResolution->permissionIds();

        $this->roleService->syncPermissions(
            $role,
            $permissionIds,
            new AuditContext(
                actor: $user,
                newValues: [
                    'permissionCount' => count($permissionIds),
                ]
            )
        );

        return $this->success([], 'Role permissions updated');
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
            ? $this->error(ApiResultCode::FORBIDDEN, 'Forbidden')
            : $this->error(ApiResultCode::PARAM_ERROR, 'Role not found');
    }
}
