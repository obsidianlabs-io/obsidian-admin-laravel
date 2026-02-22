<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ProxyTrustCheckCommandTest extends TestCase
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

    public function test_proxy_trust_check_passes_on_default_configuration(): void
    {
        putenv('TRUSTED_PROXIES=REMOTE_ADDR');
        putenv('TRUSTED_PROXY_HEADERS=DEFAULT');
        $this->syncProxyTrustConfig();

        $this->artisan('http:proxy-trust-check')
            ->expectsOutputToContain('Proxy trust configuration passed.')
            ->assertExitCode(0);
    }

    public function test_proxy_trust_check_fails_when_headers_config_is_invalid(): void
    {
        putenv('TRUSTED_PROXIES=REMOTE_ADDR');
        putenv('TRUSTED_PROXY_HEADERS=INVALID_HEADER_VALUE');
        $this->syncProxyTrustConfig();

        $this->artisan('http:proxy-trust-check')
            ->expectsOutputToContain('TRUSTED_PROXY_HEADERS=INVALID_HEADER_VALUE is invalid')
            ->assertExitCode(1);
    }

    public function test_proxy_trust_check_strict_mode_fails_for_wildcard_proxies_in_production(): void
    {
        config()->set('app.env', 'production');
        putenv('TRUSTED_PROXIES=*');
        putenv('TRUSTED_PROXY_HEADERS=AWS_ELB');
        $this->syncProxyTrustConfig();

        $this->artisan('http:proxy-trust-check --strict')
            ->expectsOutputToContain('WARN TRUSTED_PROXIES=* trusts every direct caller in production')
            ->expectsOutputToContain('strict mode failed')
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
