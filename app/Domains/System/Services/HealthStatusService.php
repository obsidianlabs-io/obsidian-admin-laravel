<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\System\Data\HealthCheckData;
use App\Domains\System\Data\HealthContextData;
use App\Domains\System\Data\HealthSnapshotData;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthStatusService
{
    public function snapshot(): HealthSnapshotData
    {
        $checks = [];

        $checks[] = $this->checkAppKey();
        $checks[] = $this->checkDatabaseConnection();
        $checks[] = $this->checkProductionGuards();

        $status = 'ok';
        foreach ($checks as $check) {
            if ($check->isFail()) {
                $status = 'fail';
                break;
            }

            if ($check->isWarn()) {
                $status = 'warn';
            }
        }

        return new HealthSnapshotData(
            status: $status,
            checks: $checks,
            context: new HealthContextData(
                environment: (string) app()->environment(),
                timezone: (string) config('app.timezone', 'UTC'),
                database: (string) config('database.default', 'unknown'),
                cacheStore: (string) config('cache.default', 'unknown'),
                queueConnection: (string) config('queue.default', 'unknown'),
                logChannel: (string) config('logging.default', 'unknown'),
            ),
        );
    }

    private function checkAppKey(): HealthCheckData
    {
        $keyConfigured = trim((string) config('app.key', '')) !== '';

        return new HealthCheckData(
            name: 'app.key',
            status: $keyConfigured ? 'ok' : 'fail',
            detail: $keyConfigured ? 'Application key is configured' : 'APP_KEY is missing',
        );
    }

    private function checkDatabaseConnection(): HealthCheckData
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (Throwable $exception) {
            return new HealthCheckData(
                name: 'database.connection',
                status: 'fail',
                detail: 'Database connection failed: '.$exception->getMessage(),
            );
        }

        return new HealthCheckData(
            name: 'database.connection',
            status: 'ok',
            detail: 'Database connection is healthy',
        );
    }

    private function checkProductionGuards(): HealthCheckData
    {
        if (! app()->environment('production')) {
            return new HealthCheckData(
                name: 'production.guards',
                status: 'ok',
                detail: 'Production guard checks are informational outside production',
            );
        }

        $warnings = [];

        if ((bool) config('app.debug', false)) {
            $warnings[] = 'APP_DEBUG=true';
        }

        if ((string) config('queue.default', 'sync') === 'sync') {
            $warnings[] = 'QUEUE_CONNECTION=sync';
        }

        if ((string) config('cache.default', 'array') === 'array') {
            $warnings[] = 'CACHE_STORE=array';
        }

        if ((string) config('session.secure', false) !== '1' && config('session.secure') !== true) {
            $warnings[] = 'SESSION_SECURE_COOKIE is not enabled';
        }

        if ($warnings === []) {
            return new HealthCheckData(
                name: 'production.guards',
                status: 'ok',
                detail: 'Production guardrails look good',
            );
        }

        return new HealthCheckData(
            name: 'production.guards',
            status: 'warn',
            detail: implode('; ', $warnings),
        );
    }
}
