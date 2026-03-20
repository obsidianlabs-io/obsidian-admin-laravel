<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('access-permission', static function (User $user, string $permissionCode): bool {
            return $user->hasPermission($permissionCode);
        });

        Gate::define('viewPulse', static function ($user = null): bool {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            if (! $user instanceof User) {
                return false;
            }

            $user->loadMissing('role:id,code');
            $role = $user->role;

            return $role instanceof Role && (string) $role->code === 'R_SUPER';
        });

        Gate::define('viewApiDocs', static function (?User $user): bool {
            if (! (bool) config('scramble.enabled', true) || $user === null) {
                return false;
            }

            $user->loadMissing('role:id,code');
            $role = $user->role;

            return $role instanceof Role && (string) $role->code === 'R_SUPER';
        });
    }
}
