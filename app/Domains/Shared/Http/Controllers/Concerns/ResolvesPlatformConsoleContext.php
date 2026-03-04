<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

trait ResolvesPlatformConsoleContext
{
    /**
     * Resolve a platform-only management context:
     * - authenticated
     * - permission granted
     * - policy allows
     * - no tenant selected (platform scope only)
     *
     * @param  class-string  $policyModelClass
     * @return array{ok: false, code: string, msg: string}|array{ok: true, user: User}
     */
    protected function resolvePlatformConsoleContext(
        Request $request,
        string $permissionCode,
        string $policyAbility,
        string $policyModelClass,
        string $tenantSelectedMessage
    ): array {
        $authResult = $this->authenticateAndAuthorize($request, 'access-api', $permissionCode);
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

        if (! Gate::forUser($user)->allows($policyAbility, $policyModelClass)) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Forbidden',
            ];
        }

        $selectedTenantRaw = trim((string) $request->header('X-Tenant-Id', '0'));
        $selectedTenantId = ctype_digit($selectedTenantRaw) ? (int) $selectedTenantRaw : 0;
        if ($selectedTenantId > 0) {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => $tenantSelectedMessage,
            ];
        }

        return [
            'ok' => true,
            'user' => $user,
        ];
    }
}
