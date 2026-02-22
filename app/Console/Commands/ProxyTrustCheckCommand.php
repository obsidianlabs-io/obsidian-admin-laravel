<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\TrustedProxyConfig;
use Illuminate\Console\Command;

class ProxyTrustCheckCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'http:proxy-trust-check {--strict : Fail when warning checks exist}';

    /**
     * @var string
     */
    protected $description = 'Validate trusted proxy and trusted proxy headers configuration';

    public function handle(): int
    {
        $appEnv = (string) config('app.env', app()->environment());
        $isProduction = strtolower($appEnv) === 'production';
        $proxiesRaw = (string) config('security.proxy_trust.proxies', 'REMOTE_ADDR');
        $headersRaw = (string) config('security.proxy_trust.headers', 'DEFAULT');

        $failures = [];
        $warnings = [];

        $proxyEntries = TrustedProxyConfig::normalizeProxiesList($proxiesRaw);
        if ($proxyEntries === []) {
            $failures[] = 'TRUSTED_PROXIES is empty. Set REMOTE_ADDR, *, or a comma-separated proxy IP/CIDR list.';
        }

        $headersMask = TrustedProxyConfig::parseHeadersMask($headersRaw);
        if ($headersMask === null) {
            $failures[] = sprintf(
                'TRUSTED_PROXY_HEADERS=%s is invalid. Use DEFAULT, AWS_ELB, FORWARDED, or supported HEADER_* combinations.',
                trim($headersRaw) === '' ? '(empty)' : $headersRaw
            );
        }

        if ($proxyEntries !== [] && in_array('*', $proxyEntries, true) && $isProduction) {
            $warnings[] = 'TRUSTED_PROXIES=* trusts every direct caller in production. Prefer explicit proxy IP/CIDR addresses.';
        }

        if (
            $proxyEntries !== []
            && count($proxyEntries) === 1
            && in_array('REMOTE_ADDR', $proxyEntries, true)
            && $isProduction
        ) {
            $warnings[] = 'TRUSTED_PROXIES=REMOTE_ADDR assumes a single trusted reverse proxy hop.';
        }

        $this->line('Proxy Trust Configuration Report');
        $this->line(str_repeat('-', 72));
        $this->line(sprintf('APP_ENV=%s', $appEnv));
        $this->line(sprintf('TRUSTED_PROXIES=%s', $proxiesRaw === '' ? '(empty)' : $proxiesRaw));
        $this->line(sprintf('TRUSTED_PROXY_HEADERS=%s', $headersRaw === '' ? '(empty)' : $headersRaw));

        if ($headersMask !== null) {
            $this->line(sprintf(
                'Resolved header mask=%d [%s]',
                $headersMask,
                implode(', ', TrustedProxyConfig::describeHeadersMask($headersMask))
            ));
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->error('FAIL '.$failure);
            }

            foreach ($warnings as $warning) {
                $this->warn('WARN '.$warning);
            }

            return self::FAILURE;
        }

        if ($warnings !== []) {
            foreach ($warnings as $warning) {
                $this->warn('WARN '.$warning);
            }

            if ((bool) $this->option('strict')) {
                $this->error('Proxy trust configuration strict mode failed.');

                return self::FAILURE;
            }

            $this->info('Proxy trust configuration passed with warnings.');

            return self::SUCCESS;
        }

        $this->info('Proxy trust configuration passed.');

        return self::SUCCESS;
    }
}
