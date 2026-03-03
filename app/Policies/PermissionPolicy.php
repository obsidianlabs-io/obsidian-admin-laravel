<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user) && $user->hasPermission('permission.view');
    }

    public function manage(User $user): bool
    {
        return $this->isSuperAdmin($user) && $user->hasPermission('permission.manage');
    }

    private function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status');

        $role = $user->getRelationValue('role');
        if (! $role instanceof Role) {
            return false;
        }

        return (string) $role->code === 'R_SUPER';
    }
}
