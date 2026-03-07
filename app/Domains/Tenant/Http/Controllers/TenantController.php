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
use App\Domains\Tenant\Actions\ListTenantsQueryAction;
use App\Domains\Tenant\Actions\UpdateTenantAction;
use App\Domains\Tenant\Data\TenantResponseData;
use App\Domains\Tenant\Data\TenantSnapshot;
use App\Domains\Tenant\Http\Resources\TenantListResource;
use App\Domains\Tenant\Models\Tenant;
use App\Http\Requests\Api\Tenant\ListTenantsRequest;
use App\Http\Requests\Api\Tenant\StoreTenantRequest;
use App\Http\Requests\Api\Tenant\UpdateTenantRequest;
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

    public function list(ListTenantsRequest $request, ListTenantsQueryAction $listTenantsQuery): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $input = $request->toDTO();

        $query = $listTenantsQuery->handle($input);

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );
            $records = TenantListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();

        $records = TenantListResource::collection(
            $query->orderBy('id')
                ->forPage($input->current, $input->size)
                ->get()
        )->resolve($request);

        return $this->success(
            $this->offsetPaginationPayload($input->current, $input->size, $total, $records)->toArray()
        );
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
                newValues: TenantSnapshot::fromModel($tenant)->toArray()
            );

            return $this->success(TenantResponseData::fromModel($tenant)->toArray(), 'Tenant created');
        });
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $tenant = $this->resolveTenant($id);
        if (! $tenant instanceof Tenant) {
            return $this->tenantNotFoundResponse();
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $tenant, 'Tenant');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $user = $authResult->requireUser();

        $oldValues = TenantSnapshot::fromModel($tenant)->toArray();
        $tenant = ($this->updateTenantAction)($tenant, $request->toDTO());

        $this->auditLogService->record(
            action: 'tenant.update',
            auditable: $tenant,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: TenantSnapshot::fromModel($tenant)->toArray()
        );

        return $this->success(TenantResponseData::fromModel($tenant, $request)->toArray(), 'Tenant updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $authResult = $this->resolveTenantConsoleContext($request, 'tenant.manage');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $tenant = $this->resolveTenant($id, ['users', 'roles', 'organizations', 'teams']);
        if (! $tenant instanceof Tenant) {
            return $this->tenantNotFoundResponse();
        }

        $user = $authResult->requireUser();

        $oldValues = TenantSnapshot::fromModel($tenant)->toArray();

        if ((string) $tenant->status === '1') {
            $tenant->forceFill(['status' => '2'])->save();
            $this->apiCacheService->bump('tenants');

            $this->auditLogService->record(
                action: 'tenant.deactivate',
                auditable: $tenant,
                actor: $user,
                request: $request,
                oldValues: $oldValues,
                newValues: TenantSnapshot::fromModel($tenant)->toArray()
            );

            return $this->deletionActionSuccess('tenant', (int) $tenant->id, 'deactivated', 'Tenant deactivated');
        }

        $assignedUsers = (int) ($tenant->users_count ?? 0);
        $assignedRoles = (int) ($tenant->roles_count ?? 0);
        $assignedOrganizations = (int) ($tenant->organizations_count ?? 0);
        $assignedTeams = (int) ($tenant->teams_count ?? 0);
        if ($assignedUsers > 0 || $assignedRoles > 0 || $assignedOrganizations > 0 || $assignedTeams > 0) {
            return $this->deleteConflict(
                resource: 'tenant',
                resourceId: (int) $tenant->id,
                dependencies: [
                    'users' => $assignedUsers,
                    'roles' => $assignedRoles,
                    'organizations' => $assignedOrganizations,
                    'teams' => $assignedTeams,
                ],
                suggestedAction: 'clean_tenant_dependencies_then_retry'
            );
        }

        ($this->deleteTenantAction)($tenant);
        $this->auditLogService->record(
            action: 'tenant.soft_delete',
            auditable: $tenant,
            actor: $user,
            request: $request,
            oldValues: $oldValues
        );

        return $this->deletionActionSuccess('tenant', (int) $id, 'soft_deleted', 'Tenant deleted');
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
     * @param  list<string>  $withCount
     */
    private function resolveTenant(int $id, array $withCount = []): ?Tenant
    {
        $query = Tenant::query();
        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        return $query->find($id);
    }

    private function tenantNotFoundResponse(): JsonResponse
    {
        return $this->error(self::PARAM_ERROR_CODE, 'Tenant not found');
    }
}
