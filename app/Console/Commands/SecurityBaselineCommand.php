<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\TrustedProxyConfig;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Laravel\Horizon\Horizon;

class SecurityBaselineCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'security:baseline {--strict : Fail when warning checks exist}';

    /**
     * @var string
     */
    protected $description = 'Run backend security baseline checks for config and API route protection';

    public function handle(): int
    {
        $checks = [
            $this->checkAppKey(),
            $this->checkLoginRateLimit(),
            $this->checkPasswordPolicy(),
            $this->checkApiAuthCoverage(),
            $this->checkPermissionCoverage(),
            $this->checkProductionHardening(),
            $this->checkProxyTrustConfiguration(),
            $this->checkOptimizeArtifacts(),
            $this->checkPennantStore(),
            $this->checkHorizonQueueProfile(),
            $this->checkPulseIngestProfile(),
            $this->checkSuperAdminTwoFactorPolicy(),
        ];

        $failCount = 0;
        $warnCount = 0;

        $this->line('Security Baseline Report');
        $this->line(str_repeat('-', 72));

        foreach ($checks as $check) {
            $this->line(sprintf(
                '%s %s%s',
                strtoupper($check['status']),
                $check['name'],
                $check['details'] === [] ? '' : sprintf(' (%d)', count($check['details']))
            ));

            foreach ($check['details'] as $detail) {
                $this->line(sprintf('  - %s', $detail));
            }

            if ($check['status'] === 'fail') {
                $failCount += 1;
            }

            if ($check['status'] === 'warn') {
                $warnCount += 1;
            }
        }

        $this->line(str_repeat('-', 72));

        if ($failCount > 0) {
            $this->error(sprintf('Security baseline failed with %d failing check(s).', $failCount));

            return self::FAILURE;
        }

        if ($warnCount > 0 && (bool) $this->option('strict')) {
            $this->error(sprintf(
                'Security baseline strict mode failed due to %d warning check(s).',
                $warnCount
            ));

            return self::FAILURE;
        }

        if ($warnCount > 0) {
            $this->warn(sprintf(
                'Security baseline passed with %d warning check(s).',
                $warnCount
            ));

            return self::SUCCESS;
        }

        $this->info('Security baseline checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkAppKey(): array
    {
        $key = trim((string) config('app.key', ''));

        if ($key === '') {
            return $this->resultFail('app_key', ['APP_KEY is missing.']);
        }

        return $this->resultPass('app_key');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkLoginRateLimit(): array
    {
        $maxAttempts = (int) config('auth.login_rate_limit.max_attempts', 0);
        $decaySeconds = (int) config('auth.login_rate_limit.decay_seconds', 0);
        $minimumAttempts = (int) config('security.baseline.minimum_login_max_attempts', 3);

        $errors = [];

        if ($maxAttempts < $minimumAttempts) {
            $errors[] = sprintf(
                'auth.login_rate_limit.max_attempts=%d is below minimum %d',
                $maxAttempts,
                $minimumAttempts
            );
        }

        if ($decaySeconds <= 0) {
            $errors[] = sprintf(
                'auth.login_rate_limit.decay_seconds=%d must be greater than 0',
                $decaySeconds
            );
        }

        return $errors === []
            ? $this->resultPass('login_rate_limit')
            : $this->resultFail('login_rate_limit', $errors);
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkPasswordPolicy(): array
    {
        $minimumPasswordLength = (int) config('security.baseline.minimum_password_length', 8);
        $passwordMin = (int) config('auth.password_policy.min', 0);

        if ($passwordMin < $minimumPasswordLength) {
            return $this->resultFail('password_policy', [
                sprintf(
                    'auth.password_policy.min=%d is below minimum %d',
                    $passwordMin,
                    $minimumPasswordLength
                ),
            ]);
        }

        return $this->resultPass('password_policy');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkApiAuthCoverage(): array
    {
        $publicPatterns = $this->publicApiRoutePatterns();
        $missing = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $apiUri = $this->normalizeApiRoutePath($route->uri());
            if ($apiUri === null) {
                continue;
            }

            if ($this->matchesPatternList($apiUri, $publicPatterns)) {
                continue;
            }

            $middlewares = $route->gatherMiddleware();
            if (! in_array('api.auth', $middlewares, true)) {
                $missing[] = sprintf('%s [%s]', $apiUri, implode(',', $this->httpMethods($route)));
            }
        }

        if ($missing !== []) {
            return $this->resultFail('api_auth_coverage', $missing);
        }

        return $this->resultPass('api_auth_coverage');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkPermissionCoverage(): array
    {
        $requiredPatterns = $this->permissionRequiredRoutePatterns();
        $missing = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $apiUri = $this->normalizeApiRoutePath($route->uri());
            if ($apiUri === null) {
                continue;
            }

            if (! $this->matchesPatternList($apiUri, $requiredPatterns)) {
                continue;
            }

            $middlewares = $route->gatherMiddleware();
            if (! in_array('api.auth', $middlewares, true)) {
                continue;
            }

            $hasPermissionMiddleware = collect($middlewares)->contains(
                static fn (string $middleware): bool => str_starts_with($middleware, 'api.permission:')
            );

            if (! $hasPermissionMiddleware) {
                $missing[] = sprintf('%s [%s]', $apiUri, implode(',', $this->httpMethods($route)));
            }
        }

        if ($missing !== []) {
            return $this->resultFail('api_permission_coverage', $missing);
        }

        return $this->resultPass('api_permission_coverage');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkProductionHardening(): array
    {
        $appEnv = (string) config('app.env', 'production');
        if ($appEnv !== 'production') {
            return $this->resultPass('production_hardening');
        }

        $details = [];

        if ((bool) config('app.debug', false)) {
            $details[] = 'APP_DEBUG must be false in production.';
        }

        if (! (bool) config('session.secure', false)) {
            $details[] = 'SESSION_SECURE_COOKIE should be true in production.';
        }

        $auditQueueEnabled = (bool) config('audit.queue.enabled', true);
        $queueConnection = (string) config('audit.queue.connection', 'sync');
        if ($auditQueueEnabled && $queueConnection === 'sync') {
            $details[] = 'AUDIT_QUEUE_CONNECTION should not be sync in production when audit queue is enabled.';
        }

        return $details === []
            ? $this->resultPass('production_hardening')
            : $this->resultFail('production_hardening', $details);
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkProxyTrustConfiguration(): array
    {
        $appEnv = strtolower((string) config('app.env', app()->environment()));
        $isProduction = $appEnv === 'production';
        $proxiesRaw = (string) config('security.proxy_trust.proxies', 'REMOTE_ADDR');
        $headersRaw = (string) config('security.proxy_trust.headers', 'DEFAULT');

        $details = [];
        $warnings = [];

        $proxyEntries = TrustedProxyConfig::normalizeProxiesList($proxiesRaw);
        if ($proxyEntries === []) {
            $details[] = 'TRUSTED_PROXIES is empty. Set REMOTE_ADDR, *, or explicit proxy IP/CIDR addresses.';
        }

        $headersMask = TrustedProxyConfig::parseHeadersMask($headersRaw);
        if ($headersMask === null) {
            $details[] = sprintf(
                'TRUSTED_PROXY_HEADERS=%s is invalid. Use DEFAULT, AWS_ELB, FORWARDED, or supported HEADER_* combinations.',
                trim($headersRaw) === '' ? '(empty)' : $headersRaw
            );
        }

        if ($details !== []) {
            return $this->resultFail('proxy_trust_config', $details);
        }

        if ($isProduction && in_array('*', $proxyEntries, true)) {
            $warnings[] = 'TRUSTED_PROXIES=* trusts every direct caller in production. Prefer explicit proxy IP/CIDR addresses.';
        }

        if ($isProduction && $proxyEntries === ['REMOTE_ADDR']) {
            $warnings[] = 'TRUSTED_PROXIES=REMOTE_ADDR assumes a single trusted reverse proxy hop.';
        }

        if ($warnings !== []) {
            return $this->resultWarn('proxy_trust_config', $warnings);
        }

        return $this->resultPass('proxy_trust_config', [
            sprintf(
                'Headers: %s',
                implode(', ', TrustedProxyConfig::describeHeadersMask(
                    $headersMask ?? TrustedProxyConfig::defaultHeadersMask()
                ))
            ),
        ]);
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkSuperAdminTwoFactorPolicy(): array
    {
        $baselineRequires2fa = (bool) config('security.baseline.require_super_admin_2fa', false);
        $superAdminRequires2fa = (bool) config('security.super_admin_require_2fa', false);

        if (! $baselineRequires2fa) {
            return $this->resultPass('super_admin_2fa_policy', [
                'Project policy does not require super admin 2FA (SECURITY_BASELINE_REQUIRE_SUPER_ADMIN_2FA=false).',
            ]);
        }

        if ($superAdminRequires2fa) {
            return $this->resultPass('super_admin_2fa_policy');
        }

        return $this->resultWarn('super_admin_2fa_policy', [
            'AUTH_SUPER_ADMIN_REQUIRE_2FA=false while SECURITY_BASELINE_REQUIRE_SUPER_ADMIN_2FA=true.',
        ]);
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkOptimizeArtifacts(): array
    {
        $appEnv = (string) config('app.env', 'production');
        if ($appEnv !== 'production') {
            return $this->resultPass('optimize_artifacts');
        }

        $details = [];

        if (! app()->configurationIsCached()) {
            $details[] = 'Configuration cache missing. Run `php artisan optimize` in deploy pipeline.';
        }

        if (! app()->routesAreCached()) {
            $details[] = 'Route cache missing. Run `php artisan optimize` in deploy pipeline.';
        }

        if (! app()->eventsAreCached()) {
            $details[] = 'Event cache missing. Run `php artisan optimize` in deploy pipeline.';
        }

        return $details === []
            ? $this->resultPass('optimize_artifacts')
            : $this->resultWarn('optimize_artifacts', $details);
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkPennantStore(): array
    {
        $store = (string) config('pennant.default', 'database');

        if (app()->environment('production') && $store !== 'database') {
            return $this->resultFail('pennant_store', [
                sprintf('pennant.default=%s must be database in production.', $store),
            ]);
        }

        if ($store !== 'database') {
            return $this->resultWarn('pennant_store', [
                sprintf('pennant.default=%s. Use database store for persistent rollout controls.', $store),
            ]);
        }

        return $this->resultPass('pennant_store');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkHorizonQueueProfile(): array
    {
        if (! class_exists(Horizon::class)) {
            return $this->resultWarn('horizon_queue_profile', ['Laravel Horizon package is not installed.']);
        }

        $queueConnection = (string) config('queue.default', 'sync');

        if (app()->environment('production') && $queueConnection !== 'redis') {
            return $this->resultFail('horizon_queue_profile', [
                sprintf('queue.default=%s should be redis when Horizon is enabled in production.', $queueConnection),
            ]);
        }

        if ($queueConnection !== 'redis') {
            return $this->resultWarn('horizon_queue_profile', [
                sprintf('queue.default=%s. Horizon performs best with redis queue backend.', $queueConnection),
            ]);
        }

        return $this->resultPass('horizon_queue_profile');
    }

    /**
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function checkPulseIngestProfile(): array
    {
        $enabled = (bool) config('pulse.enabled', false);
        if (! $enabled) {
            return $this->resultPass('pulse_ingest_profile');
        }

        $ingestDriver = (string) config('pulse.ingest.driver', 'storage');
        if (app()->environment('production') && $ingestDriver !== 'redis') {
            return $this->resultFail('pulse_ingest_profile', [
                sprintf('pulse.ingest.driver=%s should be redis in production.', $ingestDriver),
            ]);
        }

        if ($ingestDriver !== 'redis') {
            return $this->resultWarn('pulse_ingest_profile', [
                sprintf('pulse.ingest.driver=%s. Redis is recommended for large systems.', $ingestDriver),
            ]);
        }

        return $this->resultPass('pulse_ingest_profile');
    }

    /**
     * @return list<string>
     */
    private function publicApiRoutePatterns(): array
    {
        /** @var list<string> $patterns */
        $patterns = config('security.baseline.public_api_route_patterns', []);

        return $patterns;
    }

    /**
     * @return list<string>
     */
    private function permissionRequiredRoutePatterns(): array
    {
        /** @var list<string> $patterns */
        $patterns = config('security.baseline.permission_required_route_patterns', []);

        return $patterns;
    }

    /**
     * @return list<string>
     */
    private function httpMethods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            static fn (string $method): bool => $method !== 'HEAD'
        ));
    }

    private function normalizeApiRoutePath(string $uri): ?string
    {
        $normalized = ltrim($uri, '/');
        $currentVersion = trim((string) config('api.current_version', 'v1'));

        if ($currentVersion !== '' && str_starts_with($normalized, 'api/'.$currentVersion.'/')) {
            return substr($normalized, strlen('api/'.$currentVersion.'/'));
        }

        if (preg_match('/^api\/v\d+\//', $normalized) === 1) {
            return (string) preg_replace('/^api\/v\d+\//', '', $normalized, 1);
        }

        if (str_starts_with($normalized, 'api/')) {
            return substr($normalized, strlen('api/'));
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesPatternList(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $details
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function resultPass(string $name, array $details = []): array
    {
        return [
            'name' => $name,
            'status' => 'pass',
            'details' => $details,
        ];
    }

    /**
     * @param  list<string>  $details
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function resultWarn(string $name, array $details): array
    {
        return [
            'name' => $name,
            'status' => 'warn',
            'details' => $details,
        ];
    }

    /**
     * @param  list<string>  $details
     * @return array{name: string, status: 'pass'|'warn'|'fail', details: list<string>}
     */
    private function resultFail(string $name, array $details): array
    {
        return [
            'name' => $name,
            'status' => 'fail',
            'details' => $details,
        ];
    }
}
