<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\System\Listeners\RecordAsyncAuditEvent;
use App\Domains\System\Services\FeatureFlagService;
use App\Domains\Tenant\Actions\ResolveActiveTenantIdByCodeAction;
use App\Domains\Tenant\Contracts\ActiveTenantResolver;
use App\Jobs\WriteAuditLogJob;
use App\Support\RequestTraceContext;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->configureRateLimiting();
        $this->configureQueueRouting($this->app->make(QueueFactory::class));

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer', 'JWT')
                );
            });

        $featureFlagService->registerDefinitions();
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

    private function configureQueueRouting(QueueFactory $queue): void
    {
        $connection = trim((string) config('audit.queue.connection', (string) config('queue.default', 'database')));
        $queueName = trim((string) config('audit.queue.name', 'audit'));

        $queue->route([
            WriteAuditLogJob::class => [$connection !== '' ? $connection : null, $queueName !== '' ? $queueName : null],
            RecordAsyncAuditEvent::class => [$connection !== '' ? $connection : null, $queueName !== '' ? $queueName : null],
        ]);
    }

    private function configureEloquentStrictMode(): void
    {
        $enabled = (bool) config('observability.eloquent.strict_mode', ! app()->environment('production'));
        Model::shouldBeStrict($enabled);
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
                'request_id' => RequestTraceContext::requestId(),
                'trace_id' => RequestTraceContext::traceId(),
            ]);
        });
    }
}
