<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheOpenApiSpec
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        if (! (bool) config('api.docs.cache_enabled', true)) {
            return $next($request);
        }

        if (! $request->isMethod('GET') || ! $this->isSpecRequest($request)) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['body']) && is_string($cached['body'])) {
            $headers = is_array($cached['headers'] ?? null) ? $cached['headers'] : [];

            return response($cached['body'], 200, $headers);
        }

        $response = $next($request);
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        Cache::put(
            $cacheKey,
            [
                'body' => $response->getContent(),
                'headers' => [
                    'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                ],
            ],
            now()->addSeconds(max(1, (int) config('api.docs.cache_ttl_seconds', 600)))
        );

        return $response;
    }

    private function isSpecRequest(Request $request): bool
    {
        $exportPath = trim((string) config('scramble.export_path', 'api.json'), '/');
        if ($exportPath === '') {
            return false;
        }

        return trim($request->path(), '/') === 'docs/'.$exportPath;
    }

    private function cacheKey(Request $request): string
    {
        return sprintf(
            'docs.openapi.spec.%s',
            sha1($request->fullUrl().'|'.(string) config('api.current_version', 'v1'))
        );
    }
}
