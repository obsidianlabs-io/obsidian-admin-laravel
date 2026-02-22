<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\Shared\Support\TenantVisibility;
use App\Domains\System\Http\Resources\AuditLogListResource;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Audit\ListAuditLogsRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends ApiController
{
    public function __construct(private readonly TenantContextService $tenantContextService) {}

    public function list(ListAuditLogsRequest $request): JsonResponse
    {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', 'audit.view');

        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        /** @var \App\Domains\Access\Models\User $user */
        $user = $authResult['user'];

        if (! Gate::forUser($user)->allows('viewAny', AuditLog::class)) {
            return $this->error(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $roleScope = $this->tenantContextService->resolveRoleScope($request, $user);
        if (! $roleScope['ok']) {
            return $this->error($roleScope['code'], $roleScope['msg']);
        }

        $validated = $request->validated();
        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $keyword = trim((string) ($validated['keyword'] ?? ''));
        $action = trim((string) ($validated['action'] ?? ''));
        $userName = trim((string) ($validated['userName'] ?? ''));
        $dateFrom = trim((string) ($validated['dateFrom'] ?? ''));
        $dateTo = trim((string) ($validated['dateTo'] ?? ''));

        $query = AuditLog::query()
            ->with(['user:id,name', 'tenant:id,name'])
            ->select([
                'id',
                'user_id',
                'tenant_id',
                'action',
                'auditable_type',
                'auditable_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'request_id',
                'created_at',
            ]);

        $this->applyAuditVisibilityScope(
            $query,
            $roleScope['tenantId'] ?? null,
            (bool) ($roleScope['isSuper'] ?? false)
        );

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('action', 'like', '%'.$keyword.'%')
                    ->orWhere('auditable_type', 'like', '%'.$keyword.'%')
                    ->orWhere('ip_address', 'like', '%'.$keyword.'%')
                    ->orWhereHas('user', static function (Builder $userQuery) use ($keyword): void {
                        $userQuery->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        if ($action !== '') {
            $query->where('action', 'like', '%'.$action.'%');
        }

        if ($userName !== '') {
            $query->whereHas('user', static function (Builder $userQuery) use ($userName): void {
                $userQuery->where('name', 'like', '%'.$userName.'%');
            });
        }

        $userTimezone = \App\Support\ApiDateTime::requestTimezone($request);

        if ($dateFrom !== '') {
            $from = Carbon::parse($dateFrom, $userTimezone)->utc();
            $query->where('created_at', '>=', $from);
        }

        if ($dateTo !== '') {
            $to = Carbon::parse($dateTo, $userTimezone)->utc();

            // If start and end are the same timestamp, extend to end of that minute
            // so the query returns all records within that minute window
            if ($dateFrom !== '' && $dateFrom === $dateTo) {
                $to = $to->copy()->addMinute()->subSecond();
            }

            $query->where('created_at', '<=', $to);
        }

        if ($this->hasCursorPagination($validated)) {
            $page = $this->cursorPaginateById(
                clone $query,
                $size,
                (string) ($validated['cursor'] ?? ''),
                true
            );
            $records = AuditLogListResource::collection($page['records'])->resolve($request);

            return $this->success([
                'paginationMode' => 'cursor',
                'size' => $page['size'],
                'hasMore' => $page['hasMore'],
                'nextCursor' => $page['nextCursor'],
                'records' => $records,
            ]);
        }

        $total = (clone $query)->count();

        $records = AuditLogListResource::collection(
            $query->orderByDesc('id')
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

    private function applyAuditVisibilityScope(Builder $query, ?int $tenantId, bool $isSuper): void
    {
        TenantVisibility::applyScope($query, $tenantId, $isSuper);
    }
}
