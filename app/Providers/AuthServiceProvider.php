<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\System\Models\AuditLog;
use App\Domains\Tenant\Models\Organization;
use App\Domains\Tenant\Models\Team;
use App\Domains\Tenant\Models\Tenant;
use App\Policies\AuditLogPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TeamPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
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

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Team::class, TeamPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
