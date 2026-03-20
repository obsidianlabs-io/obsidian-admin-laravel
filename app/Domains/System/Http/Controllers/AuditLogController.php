<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Actions\ListAuditLogsQueryAction;
use App\Domains\System\Http\Resources\AuditLogListResource;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Audit\ListAuditLogsRequest;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends ApiController
{
    public function __construct(private readonly TenantContextService $tenantContextService) {}

    public function list(ListAuditLogsRequest $request, ListAuditLogsQueryAction $listAuditLogsQuery): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'audit.view');

        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if (! Gate::forUser($user)->allows('viewAny', AuditLog::class)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $roleScope = $this->tenantContextService->resolveRoleScope($request, $user);
        if ($roleScope->failed()) {
            return $this->error($roleScope->code(), $roleScope->message());
        }

        $input = $request->toDTO();
        $query = $listAuditLogsQuery->handle(
            $input,
            $roleScope->tenantId(),
            $roleScope->isSuper(),
            ApiDateTime::requestTimezone($request),
        );

        if ($input->usesCursorPagination((string) $request->input('paginationMode', ''))) {
            $page = $this->cursorPaginateById(
                clone $query,
                $input->size,
                $input->cursor,
                true
            );
            $records = AuditLogListResource::collection($page['records'])->resolve($request);

            return $this->success($this->cursorPaginationPayload($page, $records)->toArray());
        }

        $total = (clone $query)->count();

        $records = AuditLogListResource::collection(
            $query->latestFirst()
                ->forPage($input->current, $input->size)
                ->get()
        )->resolve($request);

        return $this->success(
            $this->offsetPaginationPayload($input->current, $input->size, $total, $records)->toArray()
        );
    }
}
