<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Access\Models\Permission;
use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Auth\Events\UserLoggedInEvent;
use App\Domains\Auth\Events\UserLoggedOutEvent;
use App\Domains\System\Events\AuditPolicyUpdatedEvent;
use App\Domains\System\Listeners\RecordAsyncAuditEvent;
use App\Domains\System\Models\AuditLog;
use App\Domains\System\Services\FeatureFlagService;
use App\Domains\Tenant\Actions\ResolveActiveTenantIdByCodeAction;
use App\Domains\Tenant\Contracts\ActiveTenantResolver;
use App\Domains\Tenant\Models\Tenant;
use App\Policies\AuditLogPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Support\ApiDateTime;
use App\Support\LocaleDefaults;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ActiveTenantResolver::class, ResolveActiveTenantIdByCodeAction::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(FeatureFlagService $featureFlagService): void
    {
        $this->configureEloquentStrictMode();
        $this->configureSlowQueryMonitor();
        $this->registerOctaneFlushHooks();
        $this->registerDomainEventListeners();
        $this->configureRateLimiting();

        Gate::define('access-permission', function (User $user, string $permissionCode): bool {
            return $user->hasPermission($permissionCode);
        });

        Gate::define('viewPulse', function ($user = null): bool {
            if (app()->environment('local', 'testing')) {
                return true;
            }

            if ($user instanceof User) {
                $user->loadMissing('role:id,code');
                $role = $user->role;

                return $role instanceof Role && (string) $role->code === 'R_SUPER';
            }

            return false;
        });

        Gate::define('viewApiDocs', function (?User $user): bool {
            if (! (bool) config('scramble.enabled', true)) {
                return false;
            }

            if ($user === null) {
                return false;
            }
            $user->loadMissing('role:id,code');
            $role = $user->role;

            return $role instanceof Role && (string) $role->code === 'R_SUPER';
        });

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                );
            });

        $featureFlagService->registerDefinitions();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('api.throttle_limit', 60))->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute((int) config('api.auth_throttle_limit', 5))->by($request->ip());
        });
    }

    private function registerDomainEventListeners(): void
    {
        Event::listen(UserLoggedInEvent::class, RecordAsyncAuditEvent::class);
        Event::listen(UserLoggedOutEvent::class, RecordAsyncAuditEvent::class);
        Event::listen(AuditPolicyUpdatedEvent::class, RecordAsyncAuditEvent::class);
    }

    /**
     * Register flush hooks for Laravel Octane (RoadRunner / Swoole).
     * Only activates when Octane is installed; safe to leave in for plain PHP-FPM.
     */
    private function registerOctaneFlushHooks(): void
    {
        if (! class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(
            \Laravel\Octane\Events\RequestReceived::class,
            static function (): void {
                ApiDateTime::flushState();
                Log::withoutContext();
                app()->setLocale(match (strtolower(str_replace('_', '-', LocaleDefaults::configured()))) {
                    'zh', 'zh-cn' => 'zh_CN',
                    'en', 'en-us' => 'en',
                    default => 'en',
                });
            }
        );
    }

    private function configureEloquentStrictMode(): void
    {
        $enabled = (bool) config('observability.eloquent.strict_mode', ! app()->environment('production'));
        if (! $enabled) {
            return;
        }

        Model::preventLazyLoading();
        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes();
    }

    private function configureSlowQueryMonitor(): void
    {
        if (! (bool) config('observability.database.log_slow_queries', true)) {
            return;
        }

        $thresholdMs = max(1, (int) config('observability.database.slow_query_threshold_ms', 200));

        DB::whenQueryingForLongerThan($thresholdMs, function (Connection $connection, QueryExecuted $event) use ($thresholdMs): void {
            Log::warning('database.slow_query_detected', [
                'connection' => $connection->getName(),
                'duration_ms' => round($event->time, 2),
                'threshold_ms' => $thresholdMs,
                'sql' => $event->sql,
                'request_id' => trim((string) (request()->attributes->get('request_id', '') ?? '')),
                'trace_id' => trim((string) (request()->attributes->get('trace_id', '') ?? '')),
            ]);
        });
    }
}
