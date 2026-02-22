<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Services;

use App\Domains\Access\Models\User;
use App\Domains\Tenant\Models\Tenant;
use Illuminate\Http\Request;

class TenantContextService
{
    public const SUCCESS_CODE = '0000';

    public const FORBIDDEN_CODE = '1003';

    /**
     * @return array{
     *   ok: bool,
     *   code: string,
     *   msg: string,
     *   tenantId?: int|null,
     *   tenantName?: string,
     *   tenants?: list<array{tenantId: string, tenantName: string}>
     * }
     */
    public function resolveTenantContext(Request $request, User $user): array
    {
        $user->loadMissing('role:id,code,level,status', 'tenant:id,name,status');

        if ($this->isSuperAdmin($user)) {
            $activeTenants = Tenant::query()
                ->where('status', '1')
                ->orderBy('id')
                ->get(['id', 'name']);

            $headerTenantId = (int) $request->header('X-Tenant-Id', 0);
            $currentTenant = $headerTenantId > 0 ? $activeTenants->firstWhere('id', $headerTenantId) : null;
            if ($headerTenantId > 0 && ! $currentTenant) {
                return [
                    'ok' => false,
                    'code' => self::FORBIDDEN_CODE,
                    'msg' => 'Selected tenant is invalid or inactive',
                ];
            }

            return [
                'ok' => true,
                'code' => self::SUCCESS_CODE,
                'msg' => 'ok',
                'tenantId' => $currentTenant ? (int) $currentTenant->id : null,
                'tenantName' => $currentTenant ? (string) $currentTenant->name : 'No Tenants',
                'tenants' => $activeTenants
                    ->map(static function (Tenant $tenant): array {
                        return [
                            'tenantId' => (string) $tenant->id,
                            'tenantName' => (string) $tenant->name,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        }

        if (! $user->tenant_id || ! $user->tenant || $user->tenant->status !== '1') {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Tenant is inactive',
            ];
        }

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'tenantId' => (int) $user->tenant->id,
            'tenantName' => (string) $user->tenant->name,
            'tenants' => [
                [
                    'tenantId' => (string) $user->tenant->id,
                    'tenantName' => (string) $user->tenant->name,
                ],
            ],
        ];
    }

    /**
     * @return array{ok: bool, code: string, msg: string, tenantId?: int|null, isSuper?: bool}
     */
    public function resolveRoleScope(Request $request, User $user): array
    {
        $user->loadMissing('role:id,code,level,status', 'tenant:id,status');

        if ($this->isSuperAdmin($user)) {
            $activeTenantIds = Tenant::query()
                ->where('status', '1')
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $headerTenantId = (int) $request->header('X-Tenant-Id', 0);
            if ($headerTenantId > 0 && ! in_array($headerTenantId, $activeTenantIds, true)) {
                return [
                    'ok' => false,
                    'code' => self::FORBIDDEN_CODE,
                    'msg' => 'Selected tenant is invalid or inactive',
                ];
            }
            $tenantId = in_array($headerTenantId, $activeTenantIds, true) ? $headerTenantId : null;

            return [
                'ok' => true,
                'code' => self::SUCCESS_CODE,
                'msg' => 'ok',
                'tenantId' => $tenantId,
                'isSuper' => true,
            ];
        }

        if (! $user->tenant_id || ! $user->tenant || $user->tenant->status !== '1') {
            return [
                'ok' => false,
                'code' => self::FORBIDDEN_CODE,
                'msg' => 'Tenant is inactive',
            ];
        }

        return [
            'ok' => true,
            'code' => self::SUCCESS_CODE,
            'msg' => 'ok',
            'tenantId' => (int) $user->tenant_id,
            'isSuper' => false,
        ];
    }

    public function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status');

        $roleCode = (string) ($user->role?->code ?? '');

        return $roleCode === 'R_SUPER';
    }
}
