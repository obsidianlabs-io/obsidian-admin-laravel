<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait ResolvesTenantScopedContext
{
    /**
     * @param  string|list<string>  $permissionCode
     * @param  class-string  $policyModelClass
     * @return array{ok: false, code: string, msg: string}|array{
     *   ok: true,
     *   code: string,
     *   msg: string,
     *   user: User,
     *   tenantId: int,
     *   tenantName: string
     * }
     */
    protected function resolveTenantScopedContextForModel(
        Request $request,
        string|array $permissionCode,
        string $ability,
        string $policyModelClass,
        TenantContextService $tenantContextService
    ): array {
        if (is_array($permissionCode)) {
            /** @var list<string> $permissionCodes */
            $permissionCodes = array_values(
                array_filter(
                    array_map(static fn (mixed $code): string => trim((string) $code), $permissionCode),
                    static fn (string $code): bool => $code !== ''
                )
            );

            if ($permissionCodes === []) {
                return [
                    'ok' => false,
                    'code' => self::FORBIDDEN_CODE,
                    'msg' => 'Forbidden',
                ];
            }

            $authResult = $this->authenticateAndAuthorizeAny($request, 'access-api', $permissionCodes);
        } else {
            $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
        }

        if (! $authResult['ok']) {
            return $authResult;
        }

        $authUser = $authResult['user'] ?? null;
        if (! $authUser instanceof User) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $user = $authUser;
        if (! Gate::forUser($user)->allows($ability, $policyModelClass)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $tenantContext = $tenantContextService->resolveTenantContext($request, $user);
        if (! $tenantContext['ok']) {
            return [
                'ok' => false,
                'code' => $tenantContext['code'],
                'msg' => $tenantContext['msg'],
            ];
        }

        $tenantId = $tenantContext['tenantId'] ?? null;
        if (! is_int($tenantId) || $tenantId <= 0) {
            return [
                'ok' => false,
                'code' => self::PARAM_ERROR_CODE,
                'msg' => 'Please select a tenant first',
            ];
        }

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'user' => $user,
            'tenantId' => $tenantId,
            'tenantName' => (string) ($tenantContext['tenantName'] ?? ''),
        ];
    }
}
