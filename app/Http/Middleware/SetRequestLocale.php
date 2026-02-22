<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\LocaleDefaults;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always set a locale for the request to avoid Octane worker locale leakage.
        $locale = $this->resolveLocale($request) ?? $this->defaultLocale();
        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        $headerLocale = $this->normalizeLocale((string) $request->header('X-Locale', ''));
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        $acceptLanguage = (string) $request->header('Accept-Language', '');
        if ($acceptLanguage === '') {
            return null;
        }

        $items = explode(',', $acceptLanguage);
        foreach ($items as $item) {
            $candidate = trim(explode(';', $item)[0] ?? '');
            $normalized = $this->normalizeLocale($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function normalizeLocale(string $locale): ?string
    {
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        if ($locale === '') {
            return null;
        }

        if ($locale === 'zh' || $locale === 'zh-cn') {
            return 'zh_CN';
        }

        if ($locale === 'en' || $locale === 'en-us') {
            return 'en';
        }

        if (str_starts_with($locale, 'zh-')) {
            return 'zh_CN';
        }

        if (str_starts_with($locale, 'en-')) {
            return 'en';
        }

        return null;
    }

    private function defaultLocale(): string
    {
        $configured = strtolower(str_replace('_', '-', LocaleDefaults::configured()));

        return match ($configured) {
            'zh', 'zh-cn' => 'zh_CN',
            'en', 'en-us' => 'en',
            default => 'en',
        };
    }
}
