<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class HealthStatusService
{
    /**
     * @return array{
     *   status: 'ok'|'warn'|'fail',
     *   checks: list<array{name: string, status: 'ok'|'warn'|'fail', detail: string}>,
     *   context: array{
     *     environment: string,
     *     timezone: string,
     *     database: string,
     *     cache_store: string,
     *     queue_connection: string,
     *     log_channel: string
     *   }
     * }
     */
    public function snapshot(): array
    {
        $checks = [];

        $checks[] = $this->checkAppKey();
        $checks[] = $this->checkDatabaseConnection();
        $checks[] = $this->checkProductionGuards();

        $status = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $status = 'fail';
                break;
            }

            if ($check['status'] === 'warn') {
                $status = 'warn';
            }
        }

        return [
            'status' => $status,
            'checks' => $checks,
            'context' => [
                'environment' => (string) app()->environment(),
                'timezone' => (string) config('app.timezone', 'UTC'),
                'database' => (string) config('database.default', 'unknown'),
                'cache_store' => (string) config('cache.default', 'unknown'),
                'queue_connection' => (string) config('queue.default', 'unknown'),
                'log_channel' => (string) config('logging.default', 'unknown'),
            ],
        ];
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function checkAppKey(): array
    {
        $keyConfigured = trim((string) config('app.key', '')) !== '';

        return [
            'name' => 'app.key',
            'status' => $keyConfigured ? 'ok' : 'fail',
            'detail' => $keyConfigured ? 'Application key is configured' : 'APP_KEY is missing',
        ];
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (Throwable $exception) {
            return [
                'name' => 'database.connection',
                'status' => 'fail',
                'detail' => 'Database connection failed: '.$exception->getMessage(),
            ];
        }

        return [
            'name' => 'database.connection',
            'status' => 'ok',
            'detail' => 'Database connection is healthy',
        ];
    }

    /**
     * @return array{name: string, status: 'ok'|'warn'|'fail', detail: string}
     */
    private function checkProductionGuards(): array
    {
        if (! app()->environment('production')) {
            return [
                'name' => 'production.guards',
                'status' => 'ok',
                'detail' => 'Production guard checks are informational outside production',
            ];
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
            return [
                'name' => 'production.guards',
                'status' => 'ok',
                'detail' => 'Production guardrails look good',
            ];
        }

        return [
            'name' => 'production.guards',
            'status' => 'warn',
            'detail' => implode('; ', $warnings),
        ];
    }
}
