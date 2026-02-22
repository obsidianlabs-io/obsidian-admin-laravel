<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class ApiCacheService
{
    public function version(string $namespace): int
    {
        return (int) Cache::get($this->versionKey($namespace), 1);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolver
     * @return TValue
     */
    public function remember(string $namespace, string $signature, Closure $resolver, int $ttlSeconds = 600): mixed
    {
        $version = $this->version($namespace);
        $cacheKey = $this->cacheKey($namespace, $version, $signature);

        return Cache::remember($cacheKey, now()->addSeconds(max(1, $ttlSeconds)), $resolver);
    }

    public function bump(string $namespace): void
    {
        $versionKey = $this->versionKey($namespace);
        $current = (int) Cache::get($versionKey, 1);

        Cache::forever($versionKey, $current + 1);
    }

    private function versionKey(string $namespace): string
    {
        return 'api.cache.version.'.trim($namespace);
    }

    private function cacheKey(string $namespace, int $version, string $signature): string
    {
        return sprintf(
            'api.cache.%s.v%d.%s',
            trim($namespace),
            $version,
            sha1($signature)
        );
    }
}
