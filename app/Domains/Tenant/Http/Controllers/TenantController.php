<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers;

use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Http\Controllers\Concerns\ResolvesPlatformConsoleContext;
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
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends ApiController
{
    use ResolvesPlatformConsoleContext;

    public function __construct(
        private readonly CreateTenantAction $createTenantAction,
        private readonly UpdateTenantAction $updateTenantAction,
        private readonly DeleteTenantAction $deleteTenantAction,
        private readonly AuditLogService $auditLogService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    public function list(ListTenantsRequest $request): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $query = Tenant::query()
            ->withCount('users')
            ->select(['id', 'code', 'name', 'status', 'created_at', 'updated_at']);
        $this->applyTenantFilters($query, $keyword, $status);

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
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
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
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }
        $user = $authResult->requireUser();

        $dto = $request->toDTO();

        return $this->withIdempotency($request, $user, function () use ($dto, $user, $request): JsonResponse {
            $tenant = ($this->createTenantAction)($dto);

            $this->auditLogService->record(
                action: 'tenant.create',
                auditable: $tenant,
                actor: $user,
                request: $request,
                newValues: $this->tenantSnapshot($tenant)
            );

            return $this->success($this->tenantResponse($tenant), 'Tenant created');
        });
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $tenantResult = $this->resolveTenant($id);
        if (! $tenantResult['ok']) {
            return $tenantResult['response'];
        }
        $tenant = $tenantResult['tenant'];

        $optimisticLockError = $this->ensureOptimisticLock($request, $tenant, 'Tenant');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $user = $authResult->requireUser();

        $oldValues = $this->tenantSnapshot($tenant);
        $tenant = ($this->updateTenantAction)($tenant, $request->toDTO());

        $this->auditLogService->record(
            action: 'tenant.update',
            auditable: $tenant,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: $this->tenantSnapshot($tenant)
        );

        return $this->success($this->tenantResponse($tenant, $request), 'Tenant updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $tenantResult = $this->resolveTenant($id, ['users', 'roles']);
        if (! $tenantResult['ok']) {
            return $tenantResult['response'];
        }
        $tenant = $tenantResult['tenant'];

        if (($tenant->users_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant has assigned users');
        }

        if (($tenant->roles_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Tenant has assigned roles');
        }

        $user = $authResult->requireUser();

        $oldValues = $this->tenantSnapshot($tenant);

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

    private function resolveTenantConsoleContext(Request $request, string $permissionCode): ApiAuthResult
    {
        $ability = $permissionCode === 'tenant.manage' ? 'manage' : 'viewAny';

        return $this->resolvePlatformConsoleContext(
            request: $request,
            permissionCode: $permissionCode,
            policyAbility: $ability,
            policyModelClass: Tenant::class,
            tenantSelectedMessage: 'Switch to No Tenant to manage tenants'
        );
    }

    /**
     * @param  Builder<Tenant>  $query
     */
    private function applyTenantFilters(Builder $query, string $keyword, string $status): void
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
    }

    /**
     * @param  list<string>  $withCount
     * @return array{ok: true, tenant: Tenant}|array{ok: false, response: JsonResponse}
     */
    private function resolveTenant(int $id, array $withCount = []): array
    {
        $query = Tenant::query();
        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $tenant = $query->find($id);
        if ($tenant instanceof Tenant) {
            return [
                'ok' => true,
                'tenant' => $tenant,
            ];
        }

        return [
            'ok' => false,
            'response' => $this->error(self::PARAM_ERROR_CODE, 'Tenant not found'),
        ];
    }

    /**
     * @return array{
     *   tenantCode: string,
     *   tenantName: string,
     *   status: string
     * }
     */
    private function tenantSnapshot(Tenant $tenant): array
    {
        return [
            'tenantCode' => (string) $tenant->code,
            'tenantName' => (string) $tenant->name,
            'status' => (string) $tenant->status,
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   tenantCode: string,
     *   tenantName: string,
     *   status: string,
     *   version?: string,
     *   updateTime?: string
     * }
     */
    private function tenantResponse(Tenant $tenant, ?Request $request = null): array
    {
        $response = [
            'id' => $tenant->id,
            ...$this->tenantSnapshot($tenant),
        ];

        if ($request instanceof Request) {
            $response['version'] = (string) ($tenant->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0);
            $response['updateTime'] = ApiDateTime::formatForRequest($tenant->updated_at, $request);
        }

        return $response;
    }
}
