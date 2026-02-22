<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\Access\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit.view');
    }
}
