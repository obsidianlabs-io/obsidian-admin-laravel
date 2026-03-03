<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\UserListResource;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Services\UserTenantScopeService;
use App\Domains\Auth\Http\Controllers\AbstractUserController;
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
    public function listUsers(ListUsersRequest $request, UserTenantScopeService $userTenantScopeService): JsonResponse
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

        $query = $userTenantScopeService->buildListQuery($authUser, $actorLevel, $tenantId, $isSuper);
        $userTenantScopeService->applyListFilters($query, $keyword, $userName, $userEmail, $roleCode, $status);

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

    public function assignUserRole(AssignUserRoleRequest $request, int $id, UserTenantScopeService $userTenantScopeService): JsonResponse
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

        $user = $userTenantScopeService->findUserInTenantScope($id, $tenantId, $isSuper);
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
            'roleCode' => $this->resolveRoleCode($user),
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

    public function createUser(CreateUserRequest $request, UserTenantScopeService $userTenantScopeService): JsonResponse
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
        $binding = $userTenantScopeService->resolveOrganizationTeamBinding(
            $targetTenantId,
            $validated['organizationId'] ?? null,
            $validated['teamId'] ?? null
        );
        if (! $binding['ok']) {
            return $this->error(self::PARAM_ERROR_CODE, $binding['msg']);
        }

        return $this->withIdempotency($request, $authUser, function () use ($validated, $roleModel, $targetTenantId, $binding, $authUser, $request): JsonResponse {
            $user = $this->userService->create(new CreateUserDTO(
                name: trim((string) $validated['userName']),
                email: trim((string) $validated['email']),
                password: (string) $validated['password'],
                status: (string) ($validated['status'] ?? '1'),
                roleId: $roleModel->id,
                tenantId: $targetTenantId,
                organizationId: $binding['organizationId'],
                teamId: $binding['teamId'],
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
                    'organizationId' => $binding['organizationId'],
                    'teamId' => $binding['teamId'],
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
                'organizationId' => $user->organization_id ? (string) $user->organization_id : null,
                'teamId' => $user->team_id ? (string) $user->team_id : null,
            ], 'User created');
        });
    }

    public function updateUser(UpdateUserRequest $request, int $id, UserTenantScopeService $userTenantScopeService): JsonResponse
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

        $user = $userTenantScopeService->findUserInTenantScope($id, $tenantId, $isSuper);
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
        $binding = $userTenantScopeService->resolveOrganizationTeamBinding(
            $targetTenantId,
            $validated['organizationId'] ?? null,
            $validated['teamId'] ?? null
        );
        if (! $binding['ok']) {
            return $this->error(self::PARAM_ERROR_CODE, $binding['msg']);
        }

        $password = (string) ($validated['password'] ?? '');

        $oldValues = [
            'userName' => $user->name,
            'email' => $user->email,
            'roleCode' => $this->resolveRoleCode($user),
            'status' => (string) $user->status,
            'organizationId' => $user->organization_id ? (int) $user->organization_id : null,
            'teamId' => $user->team_id ? (int) $user->team_id : null,
        ];
        $this->userService->update($user, new UpdateUserDTO(
            name: trim((string) $validated['userName']),
            email: trim((string) $validated['email']),
            password: $password !== '' ? $password : null,
            status: (string) ($validated['status'] ?? $user->status),
            roleId: $roleModel->id,
            tenantId: $targetTenantId,
            organizationId: $binding['organizationId'],
            teamId: $binding['teamId'],
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
                'organizationId' => $binding['organizationId'],
                'teamId' => $binding['teamId'],
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
            'organizationId' => $user->organization_id ? (string) $user->organization_id : null,
            'teamId' => $user->team_id ? (string) $user->team_id : null,
            'version' => (string) ($user->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => ApiDateTime::formatForRequest($user->updated_at, $request),
        ], 'User updated');
    }

    public function deleteUser(Request $request, int $id, UserTenantScopeService $userTenantScopeService): JsonResponse
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

        $user = $userTenantScopeService->findUserInTenantScope($id, $tenantId, $isSuper);
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

    private function resolveRoleCode(User $user): string
    {
        $role = $user->getRelationValue('role');
        if ($role instanceof Role) {
            $attributes = $role->getAttributes();
            $code = $attributes['code'] ?? null;
            if (is_string($code) && $code !== '') {
                return $code;
            }
        }

        $roleCode = $user->role_id
            ? Role::query()->whereKey((int) $user->role_id)->value('code')
            : null;

        return is_string($roleCode) ? $roleCode : '';
    }
}
