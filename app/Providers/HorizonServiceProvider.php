<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null): bool {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            if ($user instanceof User) {
                $user->loadMissing('role:id,code');
                $role = $user->role;
                if ($role instanceof Role && (string) $role->code === 'R_SUPER') {
                    return true;
                }
            }

            $allowList = array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), (array) config('horizon.allowed_emails', []))));

            return in_array((string) optional($user)->email, $allowList, true);
        });
    }
}
