<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Data\AuditPolicyPoliciesResponseData;
use App\Domains\System\Data\AuditPolicyUpdateResponseData;
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
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        return $this->success(
            AuditPolicyPoliciesResponseData::fromRecords($this->auditPolicyService->listEffectivePolicies(null))->toArray()
        );
    }

    public function history(ListAuditPolicyHistoryRequest $request): JsonResponse
    {
        $authResult = $this->authorizeAuditPolicyConsole($request, 'audit.policy.view');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $input = $request->toDTO();
        $timezone = ApiDateTime::requestTimezone($request);

        return $this->success(
            $this->auditPolicyService->listRevisionHistory($input->current, $input->size, $timezone)->toArray()
        );
    }

    public function update(UpdateAuditPoliciesRequest $request): JsonResponse
    {
        $authResult = $this->authorizeAuditPolicyConsole($request, 'audit.policy.manage');
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return $this->error(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        $input = $request->toDTO();
        $changeReason = $input->changeReason;

        try {
            $result = $this->auditPolicyService->updateGlobalPolicies(
                records: $input->records,
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
                'updated' => $result->updated,
                'revisionId' => $result->revisionId,
            ],
            actorUserId: (int) $user->id,
            tenantId: null,
        ));

        return $this->success(
            AuditPolicyUpdateResponseData::fromResult(
                $result,
                $this->auditPolicyService->listEffectivePolicies(null)
            )->toArray(),
            'Audit policy updated'
        );
    }

    private function authorizeAuditPolicyConsole(Request $request, string $permissionCode): ApiAuthResult
    {
        $authResult = $this->authenticate($request, 'access-api');
        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }
        if (! $this->tenantContextService->isSuperAdmin($user)) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        if (! $user->hasPermission($permissionCode)) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        $selectedTenantRaw = $request->header('X-Tenant-Id');
        $selectedTenantId = is_string($selectedTenantRaw) && is_numeric(trim($selectedTenantRaw))
            ? (int) trim($selectedTenantRaw)
            : 0;
        if ($selectedTenantId > 0) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Switch to No Tenant to manage audit policy');
        }

        return ApiAuthResult::success($user, $authResult->token());
    }
}
