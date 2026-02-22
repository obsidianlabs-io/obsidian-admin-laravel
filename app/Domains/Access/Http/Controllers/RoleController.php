<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\RoleListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Services\RoleService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Shared\Support\TenantVisibility;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Role\ListRolesRequest;
use App\Http\Requests\Api\Role\SyncRolePermissionsRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class RoleController extends ApiController
{
    private const RESERVED_ROLE_CODE_SUPER = 'R_SUPER';

    private const ROLE_LEVEL_MIN = 1;

    private const ROLE_LEVEL_MAX = 999;

    private const DEFAULT_ROLE_LEVEL = 100;

    public function __construct(
        private readonly RoleService $roleService,
        private readonly AuditLogService $auditLogService,
        private readonly TenantContextService $tenantContextService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function assignablePermissions(Request $request): JsonResponse
    {
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        if (! ($user->hasPermission('role.view') || $user->hasPermission('role.manage'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $scopeTenantId = $roleScope['tenantId'] ?? null;
        $isSuper = (bool) ($roleScope['isSuper'] ?? false);

        $records = $this->apiCacheService->remember(
            'permissions',
            'assignable|tenant:'.($scopeTenantId ?? 0).'|super:'.($isSuper ? 1 : 0),
            function () use ($scopeTenantId, $isSuper): array {
                return $this->buildAssignablePermissionQuery($scopeTenantId, $isSuper)
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
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'role.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $level = isset($validated['level']) ? (int) $validated['level'] : null;

        $query = Role::query()
            ->with('tenant:id,name')
            ->with('permissions:id,code,status')
            ->withCount('users')
            ->select(['id', 'code', 'name', 'description', 'status', 'tenant_id', 'level', 'created_at', 'updated_at'])
            ->where('level', '<=', $actorLevel);
        $this->applyRoleVisibilityScope($query, $roleScope['tenantId'], (bool) ($roleScope['isSuper'] ?? false));

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
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
        $authResult = $this->authenticate($request, 'access-api');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        if (! ($user->hasPermission('role.view') || $user->hasPermission('user.manage') || $user->hasPermission('user.view'))) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $scopeTenantId = $roleScope['tenantId'] ?? null;
        $isSuper = (bool) ($roleScope['isSuper'] ?? false);
        $manageableOnly = filter_var($request->query('manageableOnly', false), FILTER_VALIDATE_BOOLEAN);
        $records = $this->apiCacheService->remember(
            'roles',
            'all|tenant:'.($scopeTenantId ?? 0).'|level:'.$actorLevel.'|super:'.($isSuper ? 1 : 0).'|manageable:'.($manageableOnly ? 1 : 0),
            function () use ($actorLevel, $scopeTenantId, $isSuper, $manageableOnly): array {
                $query = Role::query()
                    ->where('status', '1')
                    ->orderBy('id');
                $this->applyRoleVisibilityScope($query, $scopeTenantId, $isSuper);
                if ($manageableOnly) {
                    $query->where(function (Builder $builder) use ($actorLevel, $scopeTenantId, $isSuper): void {
                        $builder->where('level', '<', $actorLevel);

                        if ($isSuper && $scopeTenantId === null) {
                            $builder->orWhere('code', self::RESERVED_ROLE_CODE_SUPER);
                        }
                    });
                } else {
                    $query->where('level', '<=', $actorLevel);
                }

                return $query
                    ->get(['id', 'code', 'name', 'level'])
                    ->map(static function (Role $role) use ($actorLevel, $scopeTenantId, $isSuper): array {
                        $roleLevel = max(0, (int) ($role->level ?? 0));
                        $isSuperRole = (string) $role->code === self::RESERVED_ROLE_CODE_SUPER;
                        $manageable = $roleLevel < $actorLevel || ($isSuper && $scopeTenantId === null && $isSuperRole);

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
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'role.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }
        $targetTenantId = $roleScope['tenantId'] ?? null;

        $validator = Validator::make($request->all(), [
            'roleCode' => ['required', 'string', 'max:64', $this->uniqueRoleCodeRule($targetTenantId)],
            'roleName' => ['required', 'string', 'max:100', $this->uniqueRoleNameRule($targetTenantId)],
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
        $permissionCodes = $validated['permissionCodes'] ?? [];
        $resolvedPermissions = $this->resolveAssignablePermissionIds($permissionCodes, $roleScope);
        if (! $resolvedPermissions['ok']) {
            return $this->error($resolvedPermissions['code'], $resolvedPermissions['msg']);
        }

        return $this->withIdempotency($request, $user, function () use ($validated, $targetTenantId, $resolvedPermissions, $requestedRoleLevel, $user, $request): JsonResponse {
            $role = $this->roleService->create([
                'code' => (string) $validated['roleCode'],
                'name' => (string) $validated['roleName'],
                'description' => (string) ($validated['description'] ?? ''),
                'status' => (string) ($validated['status'] ?? '1'),
                'tenant_id' => $targetTenantId,
                'level' => $requestedRoleLevel,
            ], $resolvedPermissions['ids']);

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
                    'permissionCount' => count($resolvedPermissions['ids']),
                ],
                tenantId: $targetTenantId
            );

            return $this->success([
                'id' => $role->id,
                'roleCode' => $role->code,
                'roleName' => $role->name,
                'level' => (int) $role->level,
            ], 'Role created');
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'role.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $role = $this->findRoleInScope($id, $roleScope);
        if (! $role) {
            return Role::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'Role not found');
        }
        if (! $this->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $role, 'Role');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $targetTenantId = $role->tenant_id !== null ? (int) $role->tenant_id : null;

        $validator = Validator::make($request->all(), [
            'roleCode' => ['required', 'string', 'max:64', $this->uniqueRoleCodeRule($targetTenantId, $role->id)],
            'roleName' => ['required', 'string', 'max:100', $this->uniqueRoleNameRule($targetTenantId, $role->id)],
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
        $resolvedPermissions = $this->resolveAssignablePermissionIds($validated['permissionCodes'] ?? [], $roleScope);
        if (! $resolvedPermissions['ok']) {
            return $this->error($resolvedPermissions['code'], $resolvedPermissions['msg']);
        }

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
        ], array_key_exists('permissionCodes', $validated) ? $resolvedPermissions['ids'] : null);

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

        return $this->success([
            'id' => $role->id,
            'roleCode' => $role->code,
            'roleName' => $role->name,
            'level' => (int) ($role->level ?? 0),
            'version' => (string) ($role->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => \App\Support\ApiDateTime::formatForRequest($role->updated_at, $request),
        ], 'Role updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'role.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $role = $this->findRoleInScope($id, $roleScope, true);
        if (! $role) {
            return Role::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'Role not found');
        }
        if (! $this->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        if ($role->code === 'R_SUPER') {
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
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'role.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $actorLevel = $this->resolveUserRoleLevel($user);
        if ($actorLevel <= 0) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $roleScope = $this->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $role = $this->findRoleInScope($id, $roleScope);
        if (! $role) {
            return Role::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'Role not found');
        }
        if (! $this->canManageRoleLevel($actorLevel, $role)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $role, 'Role');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $permissionCodes = $request->validated()['permissionCodes'];
        $resolvedPermissions = $this->resolveAssignablePermissionIds($permissionCodes, $roleScope);
        if (! $resolvedPermissions['ok']) {
            return $this->error($resolvedPermissions['code'], $resolvedPermissions['msg']);
        }

        $this->roleService->syncPermissions($role, $resolvedPermissions['ids']);
        $this->auditLogService->record(
            action: 'role.sync_permissions',
            auditable: $role,
            actor: $user,
            request: $request,
            newValues: [
                'permissionCount' => count($resolvedPermissions['ids']),
            ],
            tenantId: $role->tenant_id ? (int) $role->tenant_id : null
        );

        return $this->success([], 'Role permissions updated');
    }

    /**
     * @return array{ok: bool, code: string, msg: string, tenantId?: int|null, isSuper?: bool}
     */
    private function resolveRoleScope(Request $request, User $user): array
    {
        return $this->tenantContextService->resolveRoleScope($request, $user);
    }

    private function applyRoleVisibilityScope(
        Builder $query,
        ?int $tenantId,
        bool $isSuper
    ): void {
        TenantVisibility::applyScope($query, $tenantId, $isSuper);
    }

    private function uniqueRoleCodeRule(?int $tenantId, ?int $ignoreRoleId = null): Unique
    {
        $rule = Rule::unique('roles', 'code')
            ->where(function ($query) use ($tenantId): void {
                if ($tenantId === null) {
                    $query->whereNull('tenant_id');

                    return;
                }

                $query->where('tenant_id', $tenantId);
            });

        if ($ignoreRoleId !== null) {
            $rule->ignore($ignoreRoleId);
        }

        return $rule;
    }

    private function uniqueRoleNameRule(?int $tenantId, ?int $ignoreRoleId = null): Unique
    {
        $rule = Rule::unique('roles', 'name')
            ->where(function ($query) use ($tenantId): void {
                if ($tenantId === null) {
                    $query->whereNull('tenant_id');

                    return;
                }

                $query->where('tenant_id', $tenantId);
            });

        if ($ignoreRoleId !== null) {
            $rule->ignore($ignoreRoleId);
        }

        return $rule;
    }

    private function buildAssignablePermissionQuery(?int $tenantId, bool $isSuper): Builder
    {
        $query = Permission::query()->where('status', '1');

        $allowPlatformPermissions = $isSuper && $tenantId === null;
        if (! $allowPlatformPermissions) {
            $query->where(function (Builder $builder): void {
                $builder->where('code', 'not like', 'permission.%')
                    ->where('code', 'not like', 'tenant.%')
                    ->where('code', 'not like', 'language.%')
                    ->where('code', 'not like', 'audit.policy.%');
            });
        }

        return $query;
    }

    /**
     * @param  list<string>  $permissionCodes
     * @param  array{tenantId?: int|null, isSuper?: bool}  $roleScope
     * @return array{ok: bool, code: string, msg: string, ids?: list<int>}
     */
    private function resolveAssignablePermissionIds(array $permissionCodes, array $roleScope): array
    {
        $uniqueCodes = array_values(array_unique(array_map(static fn ($code): string => (string) $code, $permissionCodes)));
        if ($uniqueCodes === []) {
            return [
                'ok' => true,
                'code' => self::SUCCESS_CODE,
                'msg' => 'ok',
                'ids' => [],
            ];
        }

        $tenantId = $roleScope['tenantId'] ?? null;
        $isSuper = (bool) ($roleScope['isSuper'] ?? false);
        $assignablePermissions = $this->buildAssignablePermissionQuery($tenantId, $isSuper)
            ->whereIn('code', $uniqueCodes)
            ->get(['id', 'code']);

        if ($assignablePermissions->count() !== count($uniqueCodes)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Some permissions are not assignable in current tenant scope',
            ];
        }

        $permissionIds = $assignablePermissions
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'ids' => $permissionIds,
        ];
    }

    /**
     * @return array{code: string, msg: string}|null
     */
    private function validateReservedRoleCode(string $roleCode, ?Role $existingRole = null): ?array
    {
        $normalizedCode = strtoupper(trim($roleCode));
        if ($normalizedCode !== self::RESERVED_ROLE_CODE_SUPER) {
            return null;
        }

        $existingCode = strtoupper(trim((string) $existingRole?->code));
        if ($existingCode === self::RESERVED_ROLE_CODE_SUPER) {
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

        if ($requestedLevel >= $actorLevel) {
            return [
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Role level must be lower than your current role level',
            ];
        }

        return null;
    }

    private function resolveUserRoleLevel(\App\Domains\Access\Models\User $user): int
    {
        $roleId = (int) ($user->role_id ?? 0);
        if ($roleId <= 0) {
            return 0;
        }

        $level = Role::query()->whereKey($roleId)->value('level');

        return max(0, (int) ($level ?? 0));
    }

    private function canManageRoleLevel(int $actorLevel, Role $role): bool
    {
        return (int) $role->level < $actorLevel;
    }

    /**
     * @param  array{tenantId?: int|null, isSuper?: bool}  $roleScope
     */
    private function findRoleInScope(int $id, array $roleScope, bool $withUserCount = false): ?Role
    {
        $query = Role::query()->whereKey($id);
        if ($withUserCount) {
            $query->withCount('users');
        }

        $this->applyRoleVisibilityScope(
            $query,
            $roleScope['tenantId'] ?? null,
            (bool) ($roleScope['isSuper'] ?? false)
        );

        return $query->first();
    }
}
