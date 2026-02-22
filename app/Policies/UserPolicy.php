<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Access\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('user.view');
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('user.manage');
    }
}
