<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers;

use App\Domains\Access\Actions\ListPermissionsQueryAction;
use App\Domains\Access\Data\PermissionResponseData;
use App\Domains\Access\Data\PermissionSnapshot;
use App\Domains\Access\Http\Resources\PermissionListResource;
use App\Domains\Access\Models\Permission;
use App\Domains\Access\Services\PermissionService;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Http\Controllers\Concerns\ResolvesPlatformConsoleContext;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\DTOs\Permission\UpdatePermissionDTO;
use App\Http\Requests\Api\Permission\ListPermissionsRequest;
use App\Http\Requests\Api\Permission\StorePermissionRequest;
use App\Http\Requests\Api\Permission\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends ApiController
{
    use ResolvesPlatformConsoleContext;

    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly AuditLogService $auditLogService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function list(ListPermissionsRequest $request, ListPermissionsQueryAction $listPermissionsQuery): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $input = $request->toDTO();
        $query = $listPermissionsQuery->handle($input);

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );
            $records = PermissionListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();

        $records = PermissionListResource::collection(
            $query->orderBy('group')
                ->orderBy('id')
                ->forPage($input->current, $input->size)
                ->get()
        )->resolve($request);

        return $this->success(
            $this->offsetPaginationPayload($input->current, $input->size, $total, $records)->toArray()
        );
    }

    public function all(Request $request): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
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

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }
        $user = $authResult->requireUser();
        $input = $request->toDTO();

        return $this->withIdempotency($request, $user, function () use ($input, $user, $request): JsonResponse {
            $permission = $this->permissionService->create($input->toCreatePermissionDTO());

            $this->auditLogService->record(
                action: 'permission.create',
                auditable: $permission,
                actor: $user,
                request: $request,
                newValues: PermissionSnapshot::fromModel($permission)->toArray()
            );

            return $this->success(PermissionResponseData::fromModel($permission)->toArray(), 'Permission created');
        });
    }

    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $permission = $this->resolvePermission($id);
        if (! $permission instanceof Permission) {
            return $this->permissionNotFoundResponse();
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $permission, 'Permission');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $user = $authResult->requireUser();
        $oldValues = PermissionSnapshot::fromModel($permission)->toArray();
        $input = $request->toDTO();
        if ($input->permissionCode !== (string) $permission->code) {
            return $this->error(self::PARAM_ERROR_CODE, 'Permission code cannot be modified');
        }

        $permission = $this->permissionService->update($permission, $input->toUpdatePermissionDTO((string) $permission->status));

        $this->auditLogService->record(
            action: 'permission.update',
            auditable: $permission,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: PermissionSnapshot::fromModel($permission)->toArray()
        );

        return $this->success(PermissionResponseData::fromModel($permission, $request)->toArray(), 'Permission updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->resolvePermissionConsoleContext($request, 'permission.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $permission = $this->resolvePermission($id, ['roles']);
        if (! $permission instanceof Permission) {
            return $this->permissionNotFoundResponse();
        }

        $user = $authResult->requireUser();
        $oldValues = PermissionSnapshot::fromModel($permission)->toArray();

        if ((string) $permission->status === '1') {
            $permission = $this->permissionService->update($permission, new UpdatePermissionDTO(
                code: (string) $permission->code,
                name: (string) $permission->name,
                group: (string) ($permission->group ?? ''),
                description: (string) ($permission->description ?? ''),
                status: '2',
            ));

            $this->auditLogService->record(
                action: 'permission.deactivate',
                auditable: $permission,
                actor: $user,
                request: $request,
                oldValues: $oldValues,
                newValues: PermissionSnapshot::fromModel($permission)->toArray()
            );

            return $this->deletionActionSuccess('permission', (int) $permission->id, 'deactivated', 'Permission deactivated');
        }

        $assignedRoles = (int) ($permission->roles_count ?? 0);
        if ($assignedRoles > 0) {
            return $this->deleteConflict(
                resource: 'permission',
                resourceId: (int) $permission->id,
                dependencies: ['roles' => $assignedRoles],
                suggestedAction: 'detach_roles_then_retry'
            );
        }

        $this->permissionService->delete($permission);
        $this->auditLogService->record(
            action: 'permission.soft_delete',
            auditable: $permission,
            actor: $user,
            request: $request,
            oldValues: $oldValues
        );

        return $this->deletionActionSuccess('permission', (int) $id, 'soft_deleted', 'Permission deleted');
    }

    private function resolvePermissionConsoleContext(Request $request, string $permissionCode): ApiAuthResult
    {
        $ability = $permissionCode === 'permission.manage' ? 'manage' : 'viewAny';

        return $this->resolvePlatformConsoleContext(
            request: $request,
            permissionCode: $permissionCode,
            policyAbility: $ability,
            policyModelClass: Permission::class,
            tenantSelectedMessage: 'Switch to No Tenant to manage permissions'
        );
    }

    /**
     * @param  list<string>  $withCount
     */
    private function resolvePermission(int $id, array $withCount = []): ?Permission
    {
        $query = Permission::query();
        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        return $query->find($id);
    }

    private function permissionNotFoundResponse(): JsonResponse
    {
        return $this->error(self::PARAM_ERROR_CODE, 'Permission not found');
    }
}
