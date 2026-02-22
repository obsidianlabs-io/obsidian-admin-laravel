<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Access\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) && $user->hasPermission('tenant.view');
    }

    public function manage(User $user): bool
    {
        return $this->isSuperAdmin($user) && $user->hasPermission('tenant.manage');
    }

    private function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status');

        $roleCode = (string) ($user->role?->code ?? '');

        return $roleCode === 'R_SUPER';
    }
}
