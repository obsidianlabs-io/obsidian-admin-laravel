<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers;

use App\Domains\Shared\Auth\TenantScopedContext;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Actions\ListOrganizationsQueryAction;
use App\Domains\Tenant\Data\OrganizationResponseData;
use App\Domains\Tenant\Data\OrganizationSnapshot;
use App\Domains\Tenant\Http\Controllers\Concerns\ResolvesTenantScopedContext;
use App\Domains\Tenant\Http\Resources\OrganizationListResource;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Services\OrganizationService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Organization\ListOrganizationsRequest;
use App\Http\Requests\Api\Organization\StoreOrganizationRequest;
use App\Http\Requests\Api\Organization\UpdateOrganizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends ApiController
{
    use ResolvesTenantScopedContext;

    public function __construct(
        private readonly OrganizationService $organizationService,
        private readonly AuditLogService $auditLogService,
        private readonly TenantContextService $tenantContextService,
        private readonly ApiCacheService $apiCacheService,
    ) {}

    public function list(ListOrganizationsRequest $request, ListOrganizationsQueryAction $listOrganizationsQuery): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, ['organization.view', 'organization.manage'], 'viewAny');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $input = $request->toDTO();

        $query = $listOrganizationsQuery->handle($tenantId, $input);

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );

            $records = OrganizationListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();
        $records = OrganizationListResource::collection(
            $query->orderBy('sort')
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
        $context = $this->resolveOrganizationContext($request, ['organization.view', 'organization.manage'], 'viewAny');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $records = $this->apiCacheService->remember(
            'organizations',
            'all|tenant:'.$tenantId,
            static function () use ($tenantId): array {
                return Organization::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', '1')
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get(['id', 'code', 'name'])
                    ->map(static function (Organization $organization): array {
                        return [
                            'id' => $organization->id,
                            'organizationCode' => (string) $organization->code,
                            'organizationName' => (string) $organization->name,
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

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, 'organization.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $user = $context->requireUser();
        $dto = $request->toDTO();

        $uniqueError = $this->validateTenantUniqueness($tenantId, $dto->organizationCode, $dto->organizationName);
        if ($uniqueError !== null) {
            return $this->error(self::PARAM_ERROR_CODE, $uniqueError);
        }

        return $this->withIdempotency($request, $user, function () use ($tenantId, $dto, $user, $request): JsonResponse {
            $organization = $this->organizationService->create($tenantId, $dto);

            $this->auditLogService->record(
                action: 'organization.create',
                auditable: $organization,
                actor: $user,
                request: $request,
                newValues: [
                    ...OrganizationSnapshot::fromModel($organization)->toArray(),
                    'tenantId' => (int) $organization->tenant_id,
                ],
                tenantId: $tenantId,
            );

            return $this->success(OrganizationResponseData::fromModel($organization)->toArray(), 'Organization created');
        });
    }

    public function update(UpdateOrganizationRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, 'organization.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $user = $context->requireUser();
        $organization = $this->resolveTenantOrganization($tenantId, $id);
        if (! $organization instanceof Organization) {
            return $this->organizationScopeErrorResponse($id);
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $organization, 'Organization');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $dto = $request->toDTO();
        $uniqueError = $this->validateTenantUniqueness($tenantId, $dto->organizationCode, $dto->organizationName, $organization->id);
        if ($uniqueError !== null) {
            return $this->error(self::PARAM_ERROR_CODE, $uniqueError);
        }

        $oldValues = OrganizationSnapshot::fromModel($organization)->toArray();

        $organization = $this->organizationService->update($organization, $dto);

        $this->auditLogService->record(
            action: 'organization.update',
            auditable: $organization,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: OrganizationSnapshot::fromModel($organization)->toArray(),
            tenantId: $tenantId,
        );

        return $this->success(OrganizationResponseData::fromModel($organization, $request)->toArray(), 'Organization updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, 'organization.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $user = $context->requireUser();
        $organization = $this->resolveTenantOrganization($tenantId, $id, ['teams', 'users']);
        if (! $organization instanceof Organization) {
            return $this->organizationScopeErrorResponse($id);
        }

        $oldValues = OrganizationSnapshot::fromModel($organization)->toArray();

        if ((string) $organization->status === '1') {
            $organization->forceFill(['status' => '2'])->save();
            $this->apiCacheService->bump('organizations');

            $this->auditLogService->record(
                action: 'organization.deactivate',
                auditable: $organization,
                actor: $user,
                request: $request,
                oldValues: $oldValues,
                newValues: OrganizationSnapshot::fromModel($organization)->toArray(),
                tenantId: $tenantId,
            );

            return $this->deletionActionSuccess(
                'organization',
                (int) $organization->id,
                'deactivated',
                'Organization deactivated'
            );
        }

        $assignedTeams = (int) ($organization->teams_count ?? 0);
        $assignedUsers = (int) ($organization->users_count ?? 0);
        if ($assignedTeams > 0 || $assignedUsers > 0) {
            return $this->deleteConflict(
                resource: 'organization',
                resourceId: (int) $organization->id,
                dependencies: [
                    'teams' => $assignedTeams,
                    'users' => $assignedUsers,
                ],
                suggestedAction: 'reassign_teams_and_users_then_retry'
            );
        }

        $this->organizationService->delete($organization);

        $this->auditLogService->record(
            action: 'organization.soft_delete',
            auditable: $organization,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            tenantId: $tenantId,
        );

        return $this->deletionActionSuccess('organization', (int) $id, 'soft_deleted', 'Organization deleted');
    }

    private function validateTenantUniqueness(int $tenantId, string $code, string $name, ?int $ignoreId = null): ?string
    {
        $codeExistsQuery = Organization::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);
        $nameExistsQuery = Organization::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $name);

        if (is_int($ignoreId) && $ignoreId > 0) {
            $codeExistsQuery->where('id', '!=', $ignoreId);
            $nameExistsQuery->where('id', '!=', $ignoreId);
        }

        if ($codeExistsQuery->exists()) {
            return 'Organization code already exists in selected tenant';
        }

        if ($nameExistsQuery->exists()) {
            return 'Organization name already exists in selected tenant';
        }

        return null;
    }

    /**
     * @param  string|list<string>  $permissionCode
     */
    private function resolveOrganizationContext(
        Request $request,
        string|array $permissionCode,
        string $ability
    ): TenantScopedContext {
        return $this->resolveTenantScopedContextForModel(
            $request,
            $permissionCode,
            $ability,
            Organization::class,
            $this->tenantContextService
        );
    }

    /**
     * @param  list<string>  $withCount
     */
    private function resolveTenantOrganization(int $tenantId, int $id, array $withCount = []): ?Organization
    {
        $query = Organization::query()
            ->where('tenant_id', $tenantId);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $organization = $query->find($id);
        if ($organization instanceof Organization) {
            return $organization;
        }

        return null;
    }

    private function organizationScopeErrorResponse(int $id): JsonResponse
    {
        return Organization::query()->whereKey($id)->exists()
            ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
            : $this->error(self::PARAM_ERROR_CODE, 'Organization not found');
    }
}
