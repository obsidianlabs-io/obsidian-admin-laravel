<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers;

use App\Domains\Shared\Auth\TenantScopedContext;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\Tenant\Actions\ListTeamsQueryAction;
use App\Domains\Tenant\Data\TeamResponseData;
use App\Domains\Tenant\Data\TeamSnapshot;
use App\Domains\Tenant\Http\Controllers\Concerns\ResolvesTenantScopedContext;
use App\Domains\Tenant\Http\Resources\TeamListResource;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use App\Domains\Tenant\Services\TeamService;
use App\Domains\Tenant\Services\TenantContextService;
use App\DTOs\Team\UpdateTeamDTO;
use App\Http\Requests\Api\Team\ListTeamsRequest;
use App\Http\Requests\Api\Team\StoreTeamRequest;
use App\Http\Requests\Api\Team\UpdateTeamRequest;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends ApiController
{
    use ResolvesTenantScopedContext;

    public function __construct(
        private readonly TeamService $teamService,
        private readonly TenantContextService $tenantContextService,
        private readonly ApiCacheService $apiCacheService,
    ) {}

    public function list(ListTeamsRequest $request, ListTeamsQueryAction $listTeamsQuery): JsonResponse
    {
        $context = $this->resolveTeamContext($request, ['team.view', 'team.manage'], 'viewAny');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }
        $input = $request->toDTO();
        $organizationId = $input->organizationId;

        if ($organizationId !== null && ! $this->organizationExistsInTenant($tenantId, $organizationId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Organization not found');
        }

        $query = $listTeamsQuery->handle($tenantId, $input);

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                false
            );

            $records = TeamListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();
        $records = TeamListResource::collection(
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
        $context = $this->resolveTeamContext($request, ['team.view', 'team.manage'], 'viewAny');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }
        $organizationId = (int) $request->query('organizationId', 0);
        if ($organizationId > 0 && ! $this->organizationExistsInTenant($tenantId, $organizationId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Organization not found');
        }

        $records = $this->apiCacheService->remember(
            'teams',
            sprintf('all|tenant:%d|organization:%d', $tenantId, max(0, $organizationId)),
            static function () use ($tenantId, $organizationId): array {
                $query = Team::query()
                    ->where('tenant_id', $tenantId)
                    ->where('status', '1');

                if ($organizationId > 0) {
                    $query->where('organization_id', $organizationId);
                }

                return $query
                    ->orderBy('sort')
                    ->orderBy('id')
                    ->get(['id', 'organization_id', 'code', 'name'])
                    ->map(static function (Team $team): array {
                        return [
                            'id' => $team->id,
                            'organizationId' => (string) $team->organization_id,
                            'teamCode' => (string) $team->code,
                            'teamName' => (string) $team->name,
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

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $context = $this->resolveTeamContext($request, 'team.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }
        $user = $context->requireUser();
        $dto = $request->toDTO();

        if (! $this->organizationExistsInTenant($tenantId, $dto->organizationId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Organization not found');
        }

        $uniqueError = $this->validateTeamUniqueness($dto->organizationId, $dto->teamCode, $dto->teamName);
        if ($uniqueError !== null) {
            return $this->error(ApiResultCode::PARAM_ERROR, $uniqueError);
        }

        return $this->withIdempotency($request, $user, function () use ($tenantId, $dto, $user): JsonResponse {
            $team = $this->teamService->create(
                $tenantId,
                $dto,
                new AuditContext(
                    actor: $user,
                    tenantId: $tenantId
                )
            );

            return $this->success(TeamResponseData::fromModel($team)->toArray(), 'Team created');
        });
    }

    public function update(UpdateTeamRequest $request, int $id): JsonResponse
    {
        $context = $this->resolveTeamContext($request, 'team.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }
        $user = $context->requireUser();
        $team = $this->resolveTenantTeam($tenantId, $id, ['users']);
        if (! $team instanceof Team) {
            return $this->teamScopeErrorResponse($id);
        }

        $optimisticLockError = $this->ensureOptimisticLock($request, $team, 'Team');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $dto = $request->toDTO();
        if (! $this->organizationExistsInTenant($tenantId, $dto->organizationId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Organization not found');
        }

        $organizationChanged = (int) $team->organization_id !== $dto->organizationId;
        if ($organizationChanged && (int) ($team->users_count ?? 0) > 0) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Team has assigned users and cannot move organization');
        }

        $uniqueError = $this->validateTeamUniqueness($dto->organizationId, $dto->teamCode, $dto->teamName, $team->id);
        if ($uniqueError !== null) {
            return $this->error(ApiResultCode::PARAM_ERROR, $uniqueError);
        }

        $oldValues = TeamSnapshot::fromModel($team)->toArray();

        $team = $this->teamService->update(
            $team,
            $dto,
            new AuditContext(
                actor: $user,
                oldValues: $oldValues,
                tenantId: $tenantId
            )
        );

        return $this->success(TeamResponseData::fromModel($team, $request)->toArray(), 'Team updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $context = $this->resolveTeamContext($request, 'team.manage', 'manage');
        if ($context->failed()) {
            return $this->error($context->code(), $context->message());
        }

        $tenantId = $context->tenantId();
        if (! is_int($tenantId)) {
            return $this->error(ApiResultCode::UNAUTHORIZED, 'Unauthorized');
        }
        $user = $context->requireUser();
        $team = $this->resolveTenantTeam($tenantId, $id, ['users']);
        if (! $team instanceof Team) {
            return $this->teamScopeErrorResponse($id);
        }

        $oldValues = TeamSnapshot::fromModel($team)->toArray();

        if ((string) $team->status === '1') {
            $this->teamService->update(
                $team,
                new UpdateTeamDTO(
                    organizationId: (int) $team->organization_id,
                    teamCode: (string) $team->code,
                    teamName: (string) $team->name,
                    description: (string) ($team->description ?? ''),
                    status: '2',
                    sort: (int) ($team->sort ?? 0)
                ),
                new AuditContext(
                    actor: $user,
                    oldValues: $oldValues,
                    overrideAction: 'team.deactivate',
                    tenantId: $tenantId
                )
            );

            return $this->deletionActionSuccess('team', (int) $team->id, 'deactivated', 'Team deactivated');
        }

        $assignedUsers = (int) ($team->users_count ?? 0);
        if ($assignedUsers > 0) {
            return $this->deleteConflict(
                resource: 'team',
                resourceId: (int) $team->id,
                dependencies: ['users' => $assignedUsers],
                suggestedAction: 'reassign_users_then_retry'
            );
        }

        $this->teamService->delete(
            $team,
            new AuditContext(
                actor: $user,
                oldValues: $oldValues,
                tenantId: $tenantId
            )
        );

        return $this->deletionActionSuccess('team', (int) $id, 'soft_deleted', 'Team deleted');
    }

    private function organizationExistsInTenant(int $tenantId, int $organizationId): bool
    {
        if ($organizationId <= 0) {
            return false;
        }

        return Organization::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($organizationId)
            ->exists();
    }

    private function validateTeamUniqueness(int $organizationId, string $code, string $name, ?int $ignoreId = null): ?string
    {
        $codeExistsQuery = Team::query()
            ->where('organization_id', $organizationId)
            ->where('code', $code);
        $nameExistsQuery = Team::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name);

        if (is_int($ignoreId) && $ignoreId > 0) {
            $codeExistsQuery->where('id', '!=', $ignoreId);
            $nameExistsQuery->where('id', '!=', $ignoreId);
        }

        if ($codeExistsQuery->exists()) {
            return 'Team code already exists in selected organization';
        }

        if ($nameExistsQuery->exists()) {
            return 'Team name already exists in selected organization';
        }

        return null;
    }

    /**
     * @param  string|list<string>  $permissionCode
     */
    private function resolveTeamContext(
        Request $request,
        string|array $permissionCode,
        string $ability
    ): TenantScopedContext {
        return $this->resolveTenantScopedContextForModel(
            $request,
            $permissionCode,
            $ability,
            Team::class,
            $this->tenantContextService
        );
    }

    /**
     * @param  list<string>  $withCount
     */
    private function resolveTenantTeam(int $tenantId, int $id, array $withCount = []): ?Team
    {
        $query = Team::query()
            ->where('tenant_id', $tenantId);

        if ($withCount !== []) {
            $query->withCount($withCount);
        }

        $team = $query->find($id);
        if ($team instanceof Team) {
            return $team;
        }

        return null;
    }

    private function teamScopeErrorResponse(int $id): JsonResponse
    {
        return Team::query()->whereKey($id)->exists()
            ? $this->error(ApiResultCode::FORBIDDEN, 'Forbidden')
            : $this->error(ApiResultCode::PARAM_ERROR, 'Team not found');
    }
}
