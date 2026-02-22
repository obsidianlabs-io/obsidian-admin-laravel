<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SecurityBaselineCommandTest extends TestCase
{
    private ?string $originalTrustedProxies = null;

    private ?string $originalTrustedProxyHeaders = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTrustedProxies = getenv('TRUSTED_PROXIES') !== false ? (string) getenv('TRUSTED_PROXIES') : null;
        $this->originalTrustedProxyHeaders = getenv('TRUSTED_PROXY_HEADERS') !== false ? (string) getenv('TRUSTED_PROXY_HEADERS') : null;
        $this->syncProxyTrustConfig();
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('TRUSTED_PROXIES', $this->originalTrustedProxies);
        $this->restoreEnv('TRUSTED_PROXY_HEADERS', $this->originalTrustedProxyHeaders);
        $this->syncProxyTrustConfig();

        parent::tearDown();
    }

    public function test_security_baseline_passes_on_default_configuration(): void
    {
        $this->artisan('security:baseline')
            ->expectsOutputToContain('Security baseline passed')
            ->assertExitCode(0);
    }

    public function test_security_baseline_reports_pass_for_super_admin_2fa_policy_when_not_required(): void
    {
        config()->set('security.baseline.require_super_admin_2fa', false);
        config()->set('security.super_admin_require_2fa', false);

        $this->artisan('security:baseline')
            ->expectsOutputToContain('PASS super_admin_2fa_policy')
            ->assertExitCode(0);
    }

    public function test_security_baseline_fails_when_login_rate_limit_is_too_low(): void
    {
        config()->set('auth.login_rate_limit.max_attempts', 1);

        $this->artisan('security:baseline')
            ->expectsOutputToContain('FAIL login_rate_limit')
            ->assertExitCode(1);
    }

    public function test_security_baseline_fails_when_permission_coverage_pattern_is_not_protected(): void
    {
        config()->set('security.baseline.permission_required_route_patterns', ['auth/me']);

        $this->artisan('security:baseline')
            ->expectsOutputToContain('FAIL api_permission_coverage')
            ->expectsOutputToContain('auth/me')
            ->assertExitCode(1);
    }

    public function test_security_baseline_fails_when_trusted_proxy_headers_config_is_invalid(): void
    {
        putenv('TRUSTED_PROXIES=REMOTE_ADDR');
        putenv('TRUSTED_PROXY_HEADERS=INVALID_HEADER_VALUE');
        $this->syncProxyTrustConfig();

        $this->artisan('security:baseline')
            ->expectsOutputToContain('FAIL proxy_trust_config')
            ->expectsOutputToContain('TRUSTED_PROXY_HEADERS=INVALID_HEADER_VALUE is invalid')
            ->assertExitCode(1);
    }

    public function test_security_baseline_strict_mode_fails_on_proxy_warning_in_production(): void
    {
        config()->set('app.env', 'production');
        putenv('TRUSTED_PROXIES=*');
        putenv('TRUSTED_PROXY_HEADERS=DEFAULT');
        $this->syncProxyTrustConfig();

        $this->artisan('security:baseline --strict')
            ->expectsOutputToContain('WARN proxy_trust_config')
            ->expectsOutputToContain('TRUSTED_PROXIES=* trusts every direct caller in production')
            ->assertExitCode(1);
    }

    public function test_security_baseline_warns_when_project_requires_super_admin_2fa_but_auth_policy_disables_it(): void
    {
        config()->set('security.baseline.require_super_admin_2fa', true);
        config()->set('security.super_admin_require_2fa', false);

        $this->artisan('security:baseline')
            ->expectsOutputToContain('WARN super_admin_2fa_policy')
            ->expectsOutputToContain('SECURITY_BASELINE_REQUIRE_SUPER_ADMIN_2FA=true')
            ->assertExitCode(0);

        $this->artisan('security:baseline --strict')
            ->expectsOutputToContain('WARN super_admin_2fa_policy')
            ->assertExitCode(1);
    }

    private function restoreEnv(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);

            return;
        }

        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private function syncProxyTrustConfig(): void
    {
        config()->set('security.proxy_trust.proxies', getenv('TRUSTED_PROXIES') !== false ? (string) getenv('TRUSTED_PROXIES') : 'REMOTE_ADDR');
        config()->set('security.proxy_trust.headers', getenv('TRUSTED_PROXY_HEADERS') !== false ? (string) getenv('TRUSTED_PROXY_HEADERS') : 'DEFAULT');
    }
}
