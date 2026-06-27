<?php

declare(strict_types=1);

namespace App\Domains\Access\Http\Controllers\Concerns;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Services\RoleScopeGuardService;
use App\Domains\Shared\Http\Controllers\ApiController;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;

/**
 * Encapsulates role-specific business rule validation that depends on
 * the authenticated actor's context (tenant scope, role level).
 *
 * Extracted from RoleController to keep the controller focused on
 * coordination. These rules cannot live in FormRequest because they
 * require runtime context (actor level, tenant id) that FormRequest
 * cannot access.
 *
 * @property-read RoleScopeGuardService $roleScopeGuardService
 * @property-read ApiController $error(*)
 */
trait ValidatesRoleBusinessRules
{
    private const ROLE_LEVEL_MIN = 1;

    private const ROLE_LEVEL_MAX = 999;

    private const DEFAULT_ROLE_LEVEL = 100;

    /**
     * Reserved role code (e.g. R_SUPER) cannot be created or reassigned to a different role.
     */
    protected function validateReservedRoleCode(string $roleCode, ?Role $existingRole = null): ?JsonResponse
    {
        if ($this->roleScopeGuardService->isRoleCodeChangeAllowed($roleCode, $existingRole)) {
            return null;
        }

        return $this->error(ApiResultCode::PARAM_ERROR, 'Role code is reserved');
    }

    /**
     * Ensure role code and name are unique within the current tenant scope.
     */
    protected function validateRoleUniqueness(
        string $roleCode,
        string $roleName,
        ?int $tenantId,
        ?int $ignoreRoleId = null
    ): ?JsonResponse {
        if ($this->roleScopeGuardService->roleCodeExistsInScope($roleCode, $tenantId, $ignoreRoleId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, __('validation.unique', ['attribute' => 'role code']));
        }

        if ($this->roleScopeGuardService->roleNameExistsInScope($roleName, $tenantId, $ignoreRoleId)) {
            return $this->error(ApiResultCode::PARAM_ERROR, __('validation.unique', ['attribute' => 'role name']));
        }

        return null;
    }

    /**
     * Ensure requested role level is within allowed range and below the actor's level.
     */
    protected function validateRequestedRoleLevel(int $requestedLevel, int $actorLevel): ?JsonResponse
    {
        if ($requestedLevel < self::ROLE_LEVEL_MIN || $requestedLevel > self::ROLE_LEVEL_MAX) {
            return $this->error(
                ApiResultCode::PARAM_ERROR,
                'Role level must be between '.self::ROLE_LEVEL_MIN.' and '.self::ROLE_LEVEL_MAX
            );
        }

        if (! $this->roleScopeGuardService->isRequestedRoleLevelAllowed(
            $requestedLevel,
            $actorLevel,
            self::ROLE_LEVEL_MIN,
            self::ROLE_LEVEL_MAX
        )) {
            return $this->error(ApiResultCode::FORBIDDEN, 'Role level must be lower than your current role level');
        }

        return null;
    }
}
