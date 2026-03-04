<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Events\AuditPolicyUpdatedEvent;
use App\Domains\System\Events\SystemRealtimeUpdated;
use App\Domains\System\Services\AuditPolicyService;
use App\Domains\Tenant\Services\TenantContextService;
use App\Http\Requests\Api\Audit\ListAuditPoliciesRequest;
use App\Http\Requests\Api\Audit\ListAuditPolicyHistoryRequest;
use App\Http\Requests\Api\Audit\UpdateAuditPoliciesRequest;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AuditPolicyController extends ApiController
{
    public function __construct(
        private readonly AuditPolicyService $auditPolicyService,
        private readonly TenantContextService $tenantContextService
    ) {}

    public function list(ListAuditPoliciesRequest $request): JsonResponse
    {
        $authResult = $this->authorizeAuditPolicyConsole($request, 'audit.policy.view');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        return $this->success([
            'records' => $this->auditPolicyService->listEffectivePolicies(null),
        ]);
    }

    public function history(ListAuditPolicyHistoryRequest $request): JsonResponse
    {
        $authResult = $this->authorizeAuditPolicyConsole($request, 'audit.policy.view');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $validated = $request->validated();
        $current = (int) ($validated['current'] ?? 1);
        $size = (int) ($validated['size'] ?? 10);
        $timezone = ApiDateTime::requestTimezone($request);

        return $this->success($this->auditPolicyService->listRevisionHistory($current, $size, $timezone));
    }

    public function update(UpdateAuditPoliciesRequest $request): JsonResponse
    {
        $authResult = $this->authorizeAuditPolicyConsole($request, 'audit.policy.manage');
        if (! $authResult['ok']) {
            return $this->error($authResult['code'], $authResult['msg']);
        }

        $user = $authResult['user'] ?? null;
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $validated = $request->validated();
        $changeReason = trim((string) ($validated['changeReason'] ?? ''));

        /** @var list<array{action?: mixed, enabled?: mixed, samplingRate?: mixed, retentionDays?: mixed}> $records */
        $records = $validated['records'];

        try {
            $result = $this->auditPolicyService->updateGlobalPolicies(
                records: $records,
                changedByUserId: (int) $user->id,
                changeReason: $changeReason
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error(self::PARAM_ERROR_CODE, $exception->getMessage());
        }

        event(AuditPolicyUpdatedEvent::fromRequest($user, $request, $changeReason, $result));
        event(new SystemRealtimeUpdated(
            topic: 'audit-policy',
            action: 'audit-policy.update',
            context: [
                'updated' => $result['updated'],
                'revisionId' => $result['revisionId'],
            ],
            actorUserId: (int) $user->id,
            tenantId: null,
        ));

        return $this->success([
            'updated' => $result['updated'],
            'clearedTenantOverrides' => $result['clearedTenantOverrides'],
            'revisionId' => $result['revisionId'],
            'records' => $this->auditPolicyService->listEffectivePolicies(null),
        ], 'Audit policy updated');
    }

    /**
     * @return array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   user?: \App\Domains\Access\Models\User,
     *   token?: \Laravel\Sanctum\PersonalAccessToken
     * }
     */
    private function authorizeAuditPolicyConsole(Request $request, string $permissionCode): array
    {
        $authResult = $this->authenticate($request, 'access-api');
        if ($authResult->failed()) {
            return [
                'ok' => false,
                'code' => $authResult->code(),
                'msg' => $authResult->message(),
            ];
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return [
                'ok' => false,
                'code' => self::UNAUTHORIZED_CODE,
                'msg' => 'Unauthorized',
            ];
        }
        if (! $this->tenantContextService->isSuperAdmin($user)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        if (! $user->hasPermission($permissionCode)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantRaw = $request->header('X-Tenant-Id');
        $selectedTenantId = is_string($selectedTenantRaw) && is_numeric(trim($selectedTenantRaw))
            ? (int) trim($selectedTenantRaw)
            : 0;
        if ($selectedTenantId > 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Switch to No Tenant to manage audit policy',
            ];
        }

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'user' => $user,
        ];
    }
}
