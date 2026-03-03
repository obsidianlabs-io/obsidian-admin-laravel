<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\AuditLogService;
use App\Domains\Tenant\Http\Controllers\Concerns\ResolvesTenantScopedContext;
use App\Domains\Tenant\Http\Resources\OrganizationListResource;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Services\OrganizationService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Organization\ListOrganizationsRequest;
use App\Http\Requests\Api\Organization\StoreOrganizationRequest;
use App\Http\Requests\Api\Organization\UpdateOrganizationRequest;
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
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

    public function list(ListOrganizationsRequest $request): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, ['organization.view', 'organization.manage'], 'viewAny');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $tenantId = $this->resolveContextTenantId($context);
        if (! is_int($tenantId)) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $validated = $request->validated();

        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $status = (string) ($validated['status'] ?? '');

        $query = Organization::query()
            ->with('tenant:id,name')
            ->withCount(['teams', 'users'])
            ->select(['id', 'tenant_id', 'code', 'name', 'description', 'status', 'sort', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId);

        $this->applyOrganizationListFilters($query, $keyword, $status);

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                false
            );

            $records = OrganizationListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();
        $records = OrganizationListResource::collection(
            $query->orderBy('sort')
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
        $context = $this->resolveOrganizationContext($request, ['organization.view', 'organization.manage'], 'viewAny');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $tenantId = $this->resolveContextTenantId($context);
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
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $tenantId = $this->resolveContextTenantId($context);
        $user = $this->resolveContextUser($context);
        if (! is_int($tenantId) || ! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
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
                    ...$this->organizationSnapshot($organization),
                    'tenantId' => (int) $organization->tenant_id,
                ],
                tenantId: $tenantId,
            );

            return $this->success($this->organizationResponse($organization), 'Organization created');
        });
    }

    public function update(UpdateOrganizationRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, 'organization.manage', 'manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $tenantId = $this->resolveContextTenantId($context);
        $user = $this->resolveContextUser($context);
        if (! is_int($tenantId) || ! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $organizationResult = $this->resolveTenantOrganization($tenantId, $id);
        if (! $organizationResult['ok']) {
            return $organizationResult['response'];
        }
        $organization = $organizationResult['organization'];

        $optimisticLockError = $this->ensureOptimisticLock($request, $organization, 'Organization');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $dto = $request->toDTO();
        $uniqueError = $this->validateTenantUniqueness($tenantId, $dto->organizationCode, $dto->organizationName, $organization->id);
        if ($uniqueError !== null) {
            return $this->error(self::PARAM_ERROR_CODE, $uniqueError);
        }

        $oldValues = $this->organizationSnapshot($organization);

        $organization = $this->organizationService->update($organization, $dto);

        $this->auditLogService->record(
            action: 'organization.update',
            auditable: $organization,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            newValues: $this->organizationSnapshot($organization),
            tenantId: $tenantId,
        );

        return $this->success($this->organizationResponse($organization, $request), 'Organization updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveOrganizationContext($request, 'organization.manage', 'manage');
        if (! $context['ok']) {
            return $this->error($context['code'], $context['msg']);
        }

        $tenantId = $this->resolveContextTenantId($context);
        $user = $this->resolveContextUser($context);
        if (! is_int($tenantId) || ! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $organizationResult = $this->resolveTenantOrganization($tenantId, $id, ['teams', 'users']);
        if (! $organizationResult['ok']) {
            return $organizationResult['response'];
        }
        $organization = $organizationResult['organization'];

        if ((int) ($organization->teams_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Organization has assigned teams');
        }

        if ((int) ($organization->users_count ?? 0) > 0) {
            return $this->error(self::PARAM_ERROR_CODE, 'Organization has assigned users');
        }

        $oldValues = $this->organizationSnapshot($organization);

        $this->organizationService->delete($organization);

        $this->auditLogService->record(
            action: 'organization.delete',
            auditable: $organization,
            actor: $user,
            request: $request,
            oldValues: $oldValues,
            tenantId: $tenantId,
        );

        return $this->success([], 'Organization deleted');
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
     * @return array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   user?: \App\Domains\Access\Models\User,
     *   tenantId?: int
     * }
     */
    private function resolveOrganizationContext(Request $request, string|array $permissionCode, string $ability): array
    {
        return $this->resolveTenantScopedContextForModel(
            $request,
            $permissionCode,
            $ability,
            Organization::class,
            $this->tenantContextService
        );
    }

    /**
     * @param  Builder<Organization>  $query
     */
    private function applyOrganizationListFilters(Builder $query, string $keyword, string $status): void
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
     * @return array{ok: true, organization: Organization}|array{ok: false, response: JsonResponse}
     */
    private function resolveTenantOrganization(int $tenantId, int $id, array $withCount = []): array
    {
        $query = Organization::query()
            ->where('tenant_id', $tenantId);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $organization = $query->find($id);
        if ($organization instanceof Organization) {
            return [
                'ok' => true,
                'organization' => $organization,
            ];
        }

        return [
            'ok' => false,
            'response' => Organization::query()->whereKey($id)->exists()
                ? $this->error(self::FORBIDDEN_CODE, 'Forbidden')
                : $this->error(self::PARAM_ERROR_CODE, 'Organization not found'),
        ];
    }

    /**
     * @return array{
     *   organizationCode: string,
     *   organizationName: string,
     *   status: string,
     *   sort: int
     * }
     */
    private function organizationSnapshot(Organization $organization): array
    {
        return [
            'organizationCode' => (string) $organization->code,
            'organizationName' => (string) $organization->name,
            'status' => (string) $organization->status,
            'sort' => (int) ($organization->sort ?? 0),
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   organizationCode: string,
     *   organizationName: string,
     *   status: string,
     *   sort: int,
     *   version?: string,
     *   updateTime?: string
     * }
     */
    private function organizationResponse(Organization $organization, ?Request $request = null): array
    {
        $response = [
            'id' => $organization->id,
            ...$this->organizationSnapshot($organization),
        ];

        if ($request instanceof Request) {
            $response['version'] = (string) ($organization->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0);
            $response['updateTime'] = ApiDateTime::formatForRequest($organization->updated_at, $request);
        }

        return $response;
    }

    /**
     * @param  array{tenantId?: int}  $context
     */
    private function resolveContextTenantId(array $context): ?int
    {
        $tenantId = $context['tenantId'] ?? null;

        return is_int($tenantId) ? $tenantId : null;
    }

    /**
     * @param  array{user?: User}  $context
     */
    private function resolveContextUser(array $context): ?User
    {
        $user = $context['user'] ?? null;

        return $user instanceof User ? $user : null;
    }
}
