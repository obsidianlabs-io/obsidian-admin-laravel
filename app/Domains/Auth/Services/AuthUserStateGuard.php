<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;

final class AuthUserStateGuard
{
    public function isTenantUserWithInactiveTenant(User $user): bool
    {
        $tenantId = $user->tenant_id ? (int) $user->tenant_id : 0;
        if ($tenantId <= 0) {
            return false;
        }

        $user->loadMissing('tenant:id,status');

        if (! $user->tenant) {
            return true;
        }

        return (string) $user->tenant->status !== '1';
    }

    public function isUserWithInactiveRole(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status,tenant_id');

        if (! $user->role) {
            return true;
        }

        return (string) $user->role->status !== '1';
    }
}
