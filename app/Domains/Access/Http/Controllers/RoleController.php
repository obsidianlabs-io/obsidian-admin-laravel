<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\RoleListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\Domains\Access\Services\RoleService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Role\ListRolesRequest;
use App\Http\Requests\Api\Role\SyncRolePermissionsRequest;
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $user = $context['user'];
        if (! ($user->hasPermission('role.view') || $user->hasPermission('role.manage'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $scopeTenantId = $context['tenantId'];
        $isSuper = $context['isSuper'];

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
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $level = isset($validated['level']) ? (int) $validated['level'] : null;
        $actorLevel = $context['actorLevel'];

        $query = Role::query()
            ->with('tenant:id,name')
            ->with('permissions:id,code,status')
            ->withCount('users')
            ->select(['id', 'code', 'name', 'description', 'status', 'tenant_id', 'level', 'created_at', 'updated_at'])
            ->where('level', '<=', $actorLevel);
        $this->roleScopeGuardService->applyRoleVisibilityScope($query, $context['tenantId'], $context['isSuper']);
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
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $user = $context['user'];
        if (! ($user->hasPermission('role.view') || $user->hasPermission('user.manage') || $user->hasPermission('user.view'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $scopeTenantId = $context['tenantId'];
        $isSuper = $context['isSuper'];
        $actorLevel = $context['actorLevel'];
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

    public function store(Request $request): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }
        $user = $context['user'];
        $actorLevel = $context['actorLevel'];
        $targetTenantId = $context['tenantId'];
        $isSuper = $context['isSuper'];

        $validator = Validator::make($request->all(), [
            'roleCode' => ['required', 'string', 'max:64', $this->roleScopeGuardService->uniqueRoleCodeRule($targetTenantId)],
            'roleName' => ['required', 'string', 'max:100', $this->roleScopeGuardService->uniqueRoleNameRule($targetTenantId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
            'level' => ['required', 'integer', 'min:'.self::ROLE_LEVEL_MIN, 'max:'.self::ROLE_LEVEL_MAX],
            'permissionCodes' => ['nullable', 'array'],
            'permissionCodes.*' => ['string', Rule::exists('permissions', 'code')],
        ]);

        if ($validator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $reservedRoleCodeError = $this->validateReservedRoleCode((string) $validated['roleCode']);
        if ($reservedRoleCodeError !== null) {
            return $this->error($reservedRoleCodeError['code'], $reservedRoleCodeError['msg']);
        }
        $requestedRoleLevel = (int) ($validated['level'] ?? self::DEFAULT_ROLE_LEVEL);
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError !== null) {
            return $this->error($roleLevelError['code'], $roleLevelError['msg']);
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $this->normalizePermissionCodes($validated['permissionCodes'] ?? []),
            $targetTenantId,
            $isSuper
        );
        if (! $permissionResolution['ok']) {
            return $permissionResolution['response'];
        }
        $permissionIds = $permissionResolution['ids'];

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

    public function update(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }
        $user = $context['user'];
        $actorLevel = $context['actorLevel'];
        $roleResult = $this->resolveScopedRole($id, $context['tenantId'], $context['isSuper']);
        if (! $roleResult['ok']) {
            return $roleResult['response'];
        }
        $role = $roleResult['role'];
        if (! $this->roleScopeGuardService->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $role, 'Role');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $targetTenantId = $role->tenant_id !== null ? (int) $role->tenant_id : null;

        $validator = Validator::make($request->all(), [
            'roleCode' => ['required', 'string', 'max:64', $this->roleScopeGuardService->uniqueRoleCodeRule($targetTenantId, $role->id)],
            'roleName' => ['required', 'string', 'max:100', $this->roleScopeGuardService->uniqueRoleNameRule($targetTenantId, $role->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:1,2'],
            'level' => ['required', 'integer', 'min:'.self::ROLE_LEVEL_MIN, 'max:'.self::ROLE_LEVEL_MAX],
            'permissionCodes' => ['nullable', 'array'],
            'permissionCodes.*' => ['string', Rule::exists('permissions', 'code')],
            'version' => ['nullable', 'integer', 'min:1'],
            'updatedAt' => ['nullable', 'string', 'max:64'],
            'updateTime' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $reservedRoleCodeError = $this->validateReservedRoleCode((string) $validated['roleCode'], $role);
        if ($reservedRoleCodeError !== null) {
            return $this->error($reservedRoleCodeError['code'], $reservedRoleCodeError['msg']);
        }
        $requestedRoleLevel = (int) ($validated['level'] ?? $role->level ?? self::DEFAULT_ROLE_LEVEL);
        $roleLevelError = $this->validateRequestedRoleLevel($requestedRoleLevel, $actorLevel);
        if ($roleLevelError !== null) {
            return $this->error($roleLevelError['code'], $roleLevelError['msg']);
        }
        $permissionResolution = $this->resolveAssignablePermissions(
            $this->normalizePermissionCodes($validated['permissionCodes'] ?? []),
            $targetTenantId,
            $context['isSuper']
        );
        if (! $permissionResolution['ok']) {
            return $permissionResolution['response'];
        }
        $permissionIds = $permissionResolution['ids'];

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
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }
        $user = $context['user'];
        $actorLevel = $context['actorLevel'];
        $roleResult = $this->resolveScopedRole($id, $context['tenantId'], $context['isSuper'], true);
        if (! $roleResult['ok']) {
            return $roleResult['response'];
        }
        $role = $roleResult['role'];
        if (! $this->roleScopeGuardService->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        if ($this->roleScopeGuardService->isReservedRoleCode((string) $role->code)) {
            return $this->error(self::FORBIDDEN_CODE, 'Super role cannot be deleted');
        }

        if (($role->users_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Role has assigned users');
        }

        $oldValues = [
            'roleCode' => $role->code,
            'roleName' => $role->name,
            'tenantId' => $role->tenant_id ? (int) $role->tenant_id : null,
            'status' => (string) $role->status,
        ];

        $this->roleService->delete($role);
        $this->auditLogService->record(
            action: 'role.delete',
            auditable: $role,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            tenantId: $role->tenant_id ? (int) $role->tenant_id : null
        );

        return $this->success([], 'Role deleted');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveRoleConsoleContext(
            $request,
            $this->authenticateAndAuthorize($request, 'access-api', 'role.manage')
        );
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }
        $user = $context['user'];
        $actorLevel = $context['actorLevel'];
        $roleResult = $this->resolveScopedRole($id, $context['tenantId'], $context['isSuper']);
        if (! $roleResult['ok']) {
            return $roleResult['response'];
        }
        $role = $roleResult['role'];
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
            $context['isSuper']
        );
        if (! $permissionResolution['ok']) {
            return $permissionResolution['response'];
        }
        $permissionIds = $permissionResolution['ids'];

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

    /**
     * @param  array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   user?: User,
     *   token?: \Laravel\Sanctum\PersonalAccessToken
     * }  $authResult
     * @return array{ok: false, code: string, msg: string}|array{
     *   ok: true,
     *   user: User,
     *   actorLevel: int,
     *   tenantId: int|null,
     *   isSuper: bool
     * }
     */
    private function resolveRoleConsoleContext(Request $request, array $authResult): array
    {
        if (! $authResult['ok']) {
            return [
                'ok' => false,
                'code' => $authResult['code'],
                'msg' => $authResult['msg'],
            ];
        }

        $user = $authResult['user'] ?? null;
        if (! $user instanceof User) {
            return [
                'ok' => false,
                'code' => self::UNAUTHORIZED_CODE,
                'msg' => 'Unauthorized',
            ];
        }

        $actorLevel = $this->roleScopeGuardService->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return [
                'ok' => false,
                'code' => $roleScope['code'],
                'msg' => $roleScope['msg'],
            ];
        }

        return [
            'ok' => true,
            'user' => $user,
            'actorLevel' => $actorLevel,
            'tenantId' => $roleScope['tenantId'] ?? null,
            'isSuper' => (bool) ($roleScope['isSuper'] ?? false),
        ];
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
     * @return array{ok: true, ids: list<int>}|array{ok: false, response: JsonResponse}
     */
    private function resolveAssignablePermissions(array $permissionCodes, ?int $tenantId, bool $isSuper): array
    {
        $resolvedPermissions = $this->roleScopeGuardService->resolveAssignablePermissionIds(
            $permissionCodes,
            $tenantId,
            $isSuper
        );

        if (! $resolvedPermissions['ok']) {
            return [
                'ok' => false,
                'response' => $this->error(self::FORBIDDEN_CODE, 'Some permissions are not assignable in current tenant scope'),
            ];
        }

        return [
            'ok' => true,
            'ids' => $resolvedPermissions['ids'],
        ];
    }

    /**
     * @return array{ok: true, role: Role}|array{ok: false, response: JsonResponse}
     */
    private function resolveScopedRole(int $id, ?int $tenantId, bool $isSuper, bool $withUserCount = false): array
    {
        $role = $this->roleScopeGuardService->findRoleInScope($id, $tenantId, $isSuper, $withUserCount);
        if ($role instanceof Role) {
            return [
                'ok' => true,
                'role' => $role,
            ];
        }

        return [
            'ok' => false,
            'response' => Role::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'Role not found'),
        ];
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

    /**
     * @return array{ok: bool, code: string, msg: string, tenantId?: int|null, isSuper?: bool}
     */
    private function resolveRoleScope(Request $request, User $user): array
    {
        return $this->tenantContextService->resolveRoleScope($request, $user);
    }

    /**
     * @return array{code: string, msg: string}|null
     */
    private function validateReservedRoleCode(string $roleCode, ?Role $existingRole = null): ?array
    {
        if ($this->roleScopeGuardService->isRoleCodeChangeAllowed($roleCode, $existingRole)) {
            return null;
        }

        return [
            'code' => self::PARAM_ERROR_CODE,
            'msg' => 'Role code is reserved',
        ];
    }

    /**
     * @return array{code: string, msg: string}|null
     */
    private function validateRequestedRoleLevel(int $requestedLevel, int $actorLevel): ?array
    {
        if ($requestedLevel < self::ROLE_LEVEL_MIN || $requestedLevel > self::ROLE_LEVEL_MAX) {
            return [
                'code' => self::PARAM_ERROR_CODE,
                'msg' => 'Role level must be between '.self::ROLE_LEVEL_MIN.' and '.self::ROLE_LEVEL_MAX,
            ];
        }

        if (! $this->roleScopeGuardService->isRequestedRoleLevelAllowed(
            $requestedLevel,
            $actorLevel,
            self::ROLE_LEVEL_MIN,
            self::ROLE_LEVEL_MAX
        )) {
            return [
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Role level must be lower than your current role level',
            ];
        }

        return null;
    }
}
