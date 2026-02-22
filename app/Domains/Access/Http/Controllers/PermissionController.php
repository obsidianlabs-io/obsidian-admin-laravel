<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\PermissionListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Services\PermissionService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Http\Requests\Api\Permission\ListPermissionsRequest;
use App\Http\Requests\Api\Permission\StorePermissionRequest;
use App\Http\Requests\Api\Permission\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PermissionController extends ApiController
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly AuditLogService $auditLogService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function list(ListPermissionsRequest $request): JsonResponse
    {
        $authResult = $this->authorizePermissionConsole($request, 'permission.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');
        $group = trim((string) ($validated['group'] ?? ''));

        $query = Permission::query()
            ->withCount('roles')
            ->select(['id', 'code', 'name', 'group', 'description', 'status', 'created_at', 'updated_at']);

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('code', 'like', '%'.$keyword.'%')
                    ->orWhere('name', 'like', '%'.$keyword.'%');
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($group !== '') {
            $query->where('group', $group);
        }

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                false
            );
            $records = PermissionListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();

        $records = PermissionListResource::collection(
            $query->orderBy('group')
                ->orderBy('id')
                ->forPage($current, $size)
                ->get()
        )->resolve($request);

        return $this->success([
            'current' => $current,
            'size' => $size,
            'total' => $total,
            'records' => $records,
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $authResult = $this->authorizePermissionConsole($request, 'permission.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $records = $this->apiCacheService->remember(
            'permissions',
            'all',
            static function (): array {
                return Permission::query()
                    ->where('status', '1')
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

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $authResult = $this->authorizePermissionConsole($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $validated = $request->validated();

        return $this->withIdempotency($request, $user, function () use ($validated, $user, $request): JsonResponse {
            $permission = $this->permissionService->create([
                'code' => (string) $validated['permissionCode'],
                'name' => (string) $validated['permissionName'],
                'group' => (string) ($validated['group'] ?? ''),
                'description' => (string) ($validated['description'] ?? ''),
                'status' => (string) ($validated['status'] ?? '1'),
            ]);

            $this->auditLogService->record(
                action: 'permission.create',
                auditable: $permission,
                actor: $user,
                request: $request,
                newValues: [
                    'permissionCode' => $permission->code,
                    'permissionName' => $permission->name,
                    'status' => (string) $permission->status,
                ]
            );

            return $this->success([
                'id' => $permission->id,
                'permissionCode' => $permission->code,
                'permissionName' => $permission->name,
            ], 'Permission created');
        });
    }

    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        $authResult = $this->authorizePermissionConsole($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $permission = Permission::query()->find($id);
        if (! $permission) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission not found');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $permission, 'Permission');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $oldValues = [
            'permissionCode' => $permission->code,
            'permissionName' => $permission->name,
            'status' => (string) $permission->status,
        ];
        $validated = $request->validated();
        if ((string) $validated['permissionCode'] !== (string) $permission->code) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission code cannot be modified');
        }

        $permission = $this->permissionService->update($permission, [
            'code' => (string) $validated['permissionCode'],
            'name' => (string) $validated['permissionName'],
            'group' => (string) ($validated['group'] ?? ''),
            'description' => (string) ($validated['description'] ?? ''),
            'status' => (string) ($validated['status'] ?? $permission->status),
        ]);

        $this->auditLogService->record(
            action: 'permission.update',
            auditable: $permission,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'permissionCode' => $permission->code,
                'permissionName' => $permission->name,
                'status' => (string) $permission->status,
            ]
        );

        return $this->success([
            'id' => $permission->id,
            'permissionCode' => $permission->code,
            'permissionName' => $permission->name,
            'version' => (string) ($permission->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => \App\Support\ApiDateTime::formatForRequest($permission->updated_at, $request),
        ], 'Permission updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authorizePermissionConsole($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $permission = Permission::query()->withCount('roles')->find($id);
        if (! $permission) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission not found');
        }

        if (($permission->roles_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission is assigned to roles');
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $oldValues = [
            'permissionCode' => $permission->code,
            'permissionName' => $permission->name,
            'status' => (string) $permission->status,
        ];

        $this->permissionService->delete($permission);
        $this->auditLogService->record(
            action: 'permission.delete',
            auditable: $permission,
            actor: $user,
            request: $request,
            oldValues: $oldValues
        );

        return $this->success([], 'Permission deleted');
    }

    /**
     * @return array{ok: bool, code: string, msg: string, user?: \App\Domains\Access\Models\User, token?: \Laravel\Sanctum\PersonalAccessToken}
     */
    private function authorizePermissionConsole(Request $request, string $permissionCode): array
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if (! $authResult['ok']) {
            return $authResult;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $ability = $permissionCode === 'permission.manage' ? 'manage' : 'viewAny';
        if (! Gate::forUser($user)->allows($ability, Permission::class)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantId = (int) $request->header('X-Tenant-Id', 0);
        if ($selectedTenantId > 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Switch to No Tenant to manage permissions',
            ];
        }

        return $authResult;
    }
}
