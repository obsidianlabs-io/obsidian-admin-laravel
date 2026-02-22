<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\UserListResource;
use App\Domains\Access\Models\User;
use App\Domains\Auth\Http\Controllers\AbstractUserController;
use App\Domains\Shared\Support\TenantVisibility;
use App\DTOs\User\CreateUserDTO;
use App\DTOs\User\UpdateUserDTO;
use App\Http\Requests\Api\User\AssignUserRoleRequest;
use App\Http\Requests\Api\User\CreateUserRequest;
use App\Http\Requests\Api\User\ListUsersRequest;
use App\Http\Requests\Api\User\UpdateUserRequest;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends AbstractUserController
{
    public function listUsers(ListUsersRequest $request): JsonResponse
    {
        $context = $this->resolveUserManagementContext($request, ['user.view', 'user.manage']);
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $authUser = $context['user'];
        $actorLevel = $context['actorLevel'];
        $tenantId = $context['tenantId'];
        $isSuper = $this->isSuperAdmin($authUser);

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $userName = trim((string) ($validated['userName'] ?? ''));
        $userEmail = trim((string) ($validated['userEmail'] ?? ''));
        $roleCode = trim((string) ($validated['roleCode'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $query = User::query()
            ->with('role:id,code,name,level')
            ->select(['id', 'name', 'email', 'status', 'role_id', 'tenant_id', 'created_at', 'updated_at'])
            ->where('id', '!=', $authUser->id)
            ->where(function ($builder) use ($actorLevel): void {
                $builder->whereNull('role_id')
                    ->orWhereHas('role', function ($roleQuery) use ($actorLevel): void {
                        $roleQuery->where('level', '<=', $actorLevel);
                    });
            });

        TenantVisibility::applyScope($query, $tenantId, $isSuper);

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%');
            });
        }

        if ($userName !== '') {
            $query->where('name', 'like', '%'.$userName.'%');
        }

        if ($userEmail !== '') {
            $query->where('email', 'like', '%'.$userEmail.'%');
        }

        if ($roleCode !== '') {
            $query->whereHas('role', function ($builder) use ($roleCode): void {
                $builder->where('code', $roleCode);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                true
            );

            $request->attributes->set('actorRoleLevel', $actorLevel);
            $records = UserListResource::collection($page['records'])->resolve($request);

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
        $records = UserListResource::collection(
            $query->orderByDesc('id')
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

    public function assignUserRole(AssignUserRoleRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveUserManagementContext($request, 'user.manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $actorLevel = $context['actorLevel'];
        $tenantId = $context['tenantId'];
        /** @var \App\Domains\Access\Models\User $authUser */
        $authUser = $context['user'];
        $isSuper = $this->isSuperAdmin($authUser);

        $user = $this->findUserInTenantScope($id, $tenantId, $isSuper);
        if (! $user) {
            return User::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'User not found');
        }
        if (! $this->isUserLevelAllowed($actorLevel, $user)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $user, 'User');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $roleCode = (string) $request->validated()['roleCode'];
        $role = $this->findActiveRoleByCode($roleCode, $tenantId, (int) ($user->tenant_id ?? 0));
        if (! $role['ok']) {
            return $this->error(self::PARAM_ERROR_CODE, $role['msg']);
        }

        /** @var \App\Domains\Access\Models\Role $roleModel */
        $roleModel = $role['role'];
        if (! $this->isRoleLevelAllowed($actorLevel, $roleModel)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $targetTenantId = $user->tenant_id ? (int) $user->tenant_id : null;
        if (! $this->isRoleInTenantScope($roleModel, $targetTenantId)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Role does not belong to user tenant');
        }

        $oldValues = [
            'roleCode' => (string) ($user->role?->code ?? ''),
            'roleId' => (int) ($user->role_id ?? 0),
        ];
        $this->userService->assignRole($user, $roleModel);
        $this->auditLogService->record(
            action: 'user.assign_role',
            auditable: $user,
            actor: $authUser,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'roleCode' => $roleModel->code,
                'roleId' => $roleModel->id,
            ],
            tenantId: $targetTenantId
        );

        return $this->success([
            'userId' => (string) $user->id,
            'roleCode' => $roleModel->code,
            'roleName' => $roleModel->name,
            'version' => (string) ($user->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => ApiDateTime::formatForRequest($user->updated_at, $request),
        ], 'User role updated');
    }

    public function createUser(CreateUserRequest $request): JsonResponse
    {
        $context = $this->resolveUserManagementContext($request, 'user.manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $actorLevel = $context['actorLevel'];
        $tenantId = $context['tenantId'];

        /** @var \App\Domains\Access\Models\User $authUser */
        $authUser = $context['user'];
        $validated = $request->validated();
        $passwordValidator = Validator::make($validated, [
            'password' => ['required', 'string', 'max:100', $this->strongPasswordRule()],
        ]);
        if ($passwordValidator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $passwordValidator->errors()->first());
        }
        $roleCode = (string) $validated['roleCode'];

        $role = $this->findActiveRoleByCode($roleCode, $tenantId);
        if (! $role['ok']) {
            return $this->error(self::PARAM_ERROR_CODE, $role['msg']);
        }

        /** @var \App\Domains\Access\Models\Role $roleModel */
        $roleModel = $role['role'];
        if (! $this->isRoleLevelAllowed($actorLevel, $roleModel)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        if (! $this->isRoleInTenantScope($roleModel, $tenantId)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Role does not belong to selected tenant');
        }
        $targetTenantId = $roleModel->tenant_id !== null ? (int) $roleModel->tenant_id : null;

        return $this->withIdempotency($request, $authUser, function () use ($validated, $roleModel, $targetTenantId, $authUser, $request): JsonResponse {
            $user = $this->userService->create(new CreateUserDTO(
                name: trim((string) $validated['userName']),
                email: trim((string) $validated['email']),
                password: (string) $validated['password'],
                status: (string) ($validated['status'] ?? '1'),
                roleId: $roleModel->id,
                tenantId: $targetTenantId,
            ));
            $this->auditLogService->record(
                action: 'user.create',
                auditable: $user,
                actor: $authUser,
                request: $request,
                newValues: [
                    'userName' => $user->name,
                    'email' => $user->email,
                    'roleCode' => $roleModel->code,
                    'status' => (string) $user->status,
                ],
                tenantId: $targetTenantId
            );

            return $this->success([
                'userId' => (string) $user->id,
                'userName' => $user->name,
                'email' => $user->email,
                'roleCode' => $roleModel->code,
                'roleName' => $roleModel->name,
                'status' => (string) $user->status,
            ], 'User created');
        });
    }

    public function updateUser(UpdateUserRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveUserManagementContext($request, 'user.manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $actorLevel = $context['actorLevel'];
        $tenantId = $context['tenantId'];
        /** @var \App\Domains\Access\Models\User $authUser */
        $authUser = $context['user'];
        $isSuper = $this->isSuperAdmin($authUser);

        $user = $this->findUserInTenantScope($id, $tenantId, $isSuper);
        if (! $user) {
            return User::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'User not found');
        }
        if (! $this->isUserLevelAllowed($actorLevel, $user)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $user, 'User');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $validated = $request->validated();
        $passwordValidator = Validator::make($validated, [
            'password' => ['nullable', 'string', 'max:100', $this->strongPasswordRule()],
        ]);
        if ($passwordValidator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $passwordValidator->errors()->first());
        }
        $roleCode = (string) $validated['roleCode'];

        $role = $this->findActiveRoleByCode($roleCode, $tenantId, (int) ($user->tenant_id ?? 0));
        if (! $role['ok']) {
            return $this->error(self::PARAM_ERROR_CODE, $role['msg']);
        }

        /** @var \App\Domains\Access\Models\Role $roleModel */
        $roleModel = $role['role'];
        if (! $this->isRoleLevelAllowed($actorLevel, $roleModel)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }
        $targetTenantId = $user->tenant_id ? (int) $user->tenant_id : null;
        if (! $this->isRoleInTenantScope($roleModel, $targetTenantId)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Role does not belong to user tenant');
        }

        $password = (string) ($validated['password'] ?? '');

        $oldValues = [
            'userName' => $user->name,
            'email' => $user->email,
            'roleCode' => (string) ($user->role?->code ?? ''),
            'status' => (string) $user->status,
        ];
        $this->userService->update($user, new UpdateUserDTO(
            name: trim((string) $validated['userName']),
            email: trim((string) $validated['email']),
            password: $password !== '' ? $password : null,
            status: (string) ($validated['status'] ?? $user->status),
            roleId: $roleModel->id,
            tenantId: $targetTenantId,
        ));
        $this->auditLogService->record(
            action: 'user.update',
            auditable: $user,
            actor: $authUser,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'userName' => $user->name,
                'email' => $user->email,
                'roleCode' => $roleModel->code,
                'status' => (string) $user->status,
            ],
            tenantId: $targetTenantId
        );

        return $this->success([
            'userId' => (string) $user->id,
            'userName' => $user->name,
            'email' => $user->email,
            'roleCode' => $roleModel->code,
            'roleName' => $roleModel->name,
            'status' => (string) $user->status,
            'version' => (string) ($user->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => ApiDateTime::formatForRequest($user->updated_at, $request),
        ], 'User updated');
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveUserManagementContext($request, 'user.manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $authUser = $context['user'];
        $actorLevel = $context['actorLevel'];
        $tenantId = $context['tenantId'];
        $isSuper = $this->isSuperAdmin($authUser);

        if ($authUser->id === $id) {
            return $this->error(self::FORBIDDEN_CODE, 'Current user cannot be deleted');
        }

        $user = $this->findUserInTenantScope($id, $tenantId, $isSuper);
        if (! $user) {
            return User::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'User not found');
        }
        if (! $this->isUserLevelAllowed($actorLevel, $user)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $oldValues = [
            'userName' => $user->name,
            'email' => $user->email,
            'status' => (string) $user->status,
        ];
        $this->userService->delete($user);
        $this->auditLogService->record(
            action: 'user.delete',
            auditable: $user,
            actor: $authUser,
            request: $request,
            oldValues: $oldValues,
            tenantId: $user->tenant_id ? (int) $user->tenant_id : null
        );

        return $this->success([], 'User deleted');
    }

    private function findUserInTenantScope(int $id, ?int $tenantId, bool $isSuper): ?User
    {
        $query = User::query()->whereKey($id);
        TenantVisibility::applyScope($query, $tenantId, $isSuper);

        return $query->first();
    }
}
