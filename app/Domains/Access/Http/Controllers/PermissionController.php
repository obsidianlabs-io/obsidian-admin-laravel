<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Http\Resources\PermissionListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\User;
use App\Domains\Access\Services\PermissionService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Http\Requests\Api\Permission\ListPermissionsRequest;
use App\Http\Requests\Api\Permission\StorePermissionRequest;
use App\Http\Requests\Api\Permission\UpdatePermissionRequest;
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
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
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.view');

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
        $this->applyPermissionFilters($query, $keyword, $status, $group);

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
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.view');

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
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
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
                newValues: $this->permissionSnapshot($permission)
            );

            return $this->success($this->permissionResponse($permission), 'Permission created');
        });
    }

    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $permissionResult = $this->resolvePermission($id);
        if (! $permissionResult['ok']) {
            return $permissionResult['response'];
        }
        $permission = $permissionResult['permission'];

        $optimisticLockError = $this->ensureOptimisticLock($request, $permission, 'Permission');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $user = $authResult['user'];
        $oldValues = $this->permissionSnapshot($permission);
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
            newValues: $this->permissionSnapshot($permission)
        );

        return $this->success($this->permissionResponse($permission, $request), 'Permission updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $permissionResult = $this->resolvePermission($id, ['roles']);
        if (! $permissionResult['ok']) {
            return $permissionResult['response'];
        }
        $permission = $permissionResult['permission'];

        if (($permission->roles_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission is assigned to roles');
        }
        $user = $authResult['user'];
        $oldValues = $this->permissionSnapshot($permission);

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
     * @return array{ok: false, code: string, msg: string}|array{ok: true, user: User}
     */
    private function resolvePermissionConsoleContext(Request $request, string $permissionCode): array
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
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

        $ability = $permissionCode === 'permission.manage' ? 'manage' : 'viewAny';
        if (! Gate::forUser($user)->allows($ability, Permission::class)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantId = (int) $request->header('X-Tenant-Id', '0');
        if ($selectedTenantId > 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Switch to No Tenant to manage permissions',
            ];
        }

        return [
            'ok' => true,
            'user' => $user,
        ];
    }

    /**
     * @param  Builder<Permission>  $query
     */
    private function applyPermissionFilters(Builder $query, string $keyword, string $status, string $group): void
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

        if ($group !== '') {
            $query->where('group', $group);
        }
    }

    /**
     * @param  list<string>  $withCount
     * @return array{ok: true, permission: Permission}|array{ok: false, response: JsonResponse}
     */
    private function resolvePermission(int $id, array $withCount = []): array
    {
        $query = Permission::query();
        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $permission = $query->find($id);
        if ($permission instanceof Permission) {
            return [
                'ok' => true,
                'permission' => $permission,
            ];
        }

        return [
            'ok' => false,
            'response' => $this->error(self::PARAM_ERROR_CODE, 'Permission not found'),
        ];
    }

    /**
     * @return array{
     *   permissionCode: string,
     *   permissionName: string,
     *   status: string
     * }
     */
    private function permissionSnapshot(Permission $permission): array
    {
        return [
            'permissionCode' => (string) $permission->code,
            'permissionName' => (string) $permission->name,
            'status' => (string) $permission->status,
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   permissionCode: string,
     *   permissionName: string,
     *   version?: string,
     *   updateTime?: string
     * }
     */
    private function permissionResponse(Permission $permission, ?Request $request = null): array
    {
        $response = [
            'id' => $permission->id,
            'permissionCode' => (string) $permission->code,
            'permissionName' => (string) $permission->name,
        ];

        if ($request instanceof Request) {
            $response['version'] = (string) ($permission->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0);
            $response['updateTime'] = ApiDateTime::formatForRequest($permission->updated_at, $request);
        }

        return $response;
    }
}
