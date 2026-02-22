<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers;

use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Actions\CreateTenantAction;
use App\Domains\Tenant\Actions\DeleteTenantAction;
use App\Domains\Tenant\Actions\UpdateTenantAction;
use App\Domains\Tenant\Http\Resources\TenantListResource;
use App\Domains\Tenant\Models\Tenant;
use App\Http\Requests\Api\Tenant\ListTenantsRequest;
use App\Http\Requests\Api\Tenant\StoreTenantRequest;
use App\Http\Requests\Api\Tenant\UpdateTenantRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TenantController extends ApiController
{
    public function __construct(
        private readonly CreateTenantAction $createTenantAction,
        private readonly UpdateTenantAction $updateTenantAction,
        private readonly DeleteTenantAction $deleteTenantAction,
        private readonly AuditLogService $auditLogService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function list(ListTenantsRequest $request): JsonResponse
    {
        $authResult = $this->authorizeTenantConsole($request, 'tenant.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $query = Tenant::query()
            ->withCount('users')
            ->select(['id', 'code', 'name', 'status', 'created_at', 'updated_at']);

        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->where('code', 'like', '%'.$keyword.'%')
                    ->orWhere('name', 'like', '%'.$keyword.'%');
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
                false
            );
            $records = TenantListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();

        $records = TenantListResource::collection(
            $query->orderBy('id')
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
        $authResult = $this->authorizeTenantConsole($request, 'tenant.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $records = $this->apiCacheService->remember(
            'tenants',
            'all',
            static function (): array {
                return Tenant::query()
                    ->where('status', '1')
                    ->orderBy('id')
                    ->get(['id', 'code', 'name'])
                    ->map(static function (Tenant $tenant): array {
                        return [
                            'id' => $tenant->id,
                            'tenantCode' => $tenant->code,
                            'tenantName' => $tenant->name,
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

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $authResult = $this->authorizeTenantConsole($request, 'tenant.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }
        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        $dto = $request->toDTO();

        return $this->withIdempotency($request, $user, function () use ($dto, $user, $request): JsonResponse {
            $tenant = ($this->createTenantAction)($dto);

            $this->auditLogService->record(
                action: 'tenant.create',
                auditable: $tenant,
                actor: $user,
                request: $request,
                newValues: [
                    'tenantCode' => $tenant->code,
                    'tenantName' => $tenant->name,
                    'status' => (string) $tenant->status,
                ]
            );

            return $this->success([
                'id' => $tenant->id,
                'tenantCode' => $tenant->code,
                'tenantName' => $tenant->name,
                'status' => (string) $tenant->status,
            ], 'Tenant created');
        });
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeTenantConsole($request, 'tenant.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $tenant = Tenant::query()->find($id);
        if (! $tenant) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant not found');
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $tenant, 'Tenant');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        $oldValues = [
            'tenantCode' => $tenant->code,
            'tenantName' => $tenant->name,
            'status' => (string) $tenant->status,
        ];
        $tenant = ($this->updateTenantAction)($tenant, $request->toDTO());

        $this->auditLogService->record(
            action: 'tenant.update',
            auditable: $tenant,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: [
                'tenantCode' => $tenant->code,
                'tenantName' => $tenant->name,
                'status' => (string) $tenant->status,
            ]
        );

        return $this->success([
            'id' => $tenant->id,
            'tenantCode' => $tenant->code,
            'tenantName' => $tenant->name,
            'status' => (string) $tenant->status,
            'version' => (string) ($tenant->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'updateTime' => \App\Support\ApiDateTime::formatForRequest($tenant->updated_at, $request),
        ], 'Tenant updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->authorizeTenantConsole($request, 'tenant.manage');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $tenant = Tenant::query()->withCount(['users', 'roles'])->find($id);
        if (! $tenant) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant not found');
        }

        if (($tenant->users_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant has assigned users');
        }

        if (($tenant->roles_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant has assigned roles');
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        $oldValues = [
            'tenantCode' => $tenant->code,
            'tenantName' => $tenant->name,
            'status' => (string) $tenant->status,
        ];

        ($this->deleteTenantAction)($tenant);
        $this->auditLogService->record(
            action: 'tenant.delete',
            auditable: $tenant,
            actor: $user,
            request: $request,
            oldValues: $oldValues
        );

        return $this->success([], 'Tenant deleted');
    }

    /**
     * @return array{ok: bool, code: string, msg: string, user?: \App\Domains\Access\Models\User, token?: \Laravel\Sanctum\PersonalAccessToken}
     */
    private function authorizeTenantConsole(Request $request, string $permissionCode): array
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        if (! $authResult['ok']) {
            return $authResult;
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];
        $ability = $permissionCode === 'tenant.manage' ? 'manage' : 'viewAny';
        if (! Gate::forUser($user)->allows($ability, Tenant::class)) {
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
                'msg' => 'Switch to No Tenant to manage tenants',
            ];
        }

        return $authResult;
    }
}
