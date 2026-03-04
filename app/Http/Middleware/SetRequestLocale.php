<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AppLocale;
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
        $locale = $this->resolveLocale($request) ?? AppLocale::defaultFrameworkLocale();
        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): ?string
    {
        $headerLocale = AppLocale::toFrameworkLocale((string) $request->header('X-Locale', ''));
        if ($headerLocale !== null) {
            return $headerLocale;
        }

        $acceptLanguage = (string) $request->header('Accept-Language', '');
        if ($acceptLanguage === '') {
            return null;
        }

        $items = explode(',', $acceptLanguage);
        foreach ($items as $item) {
            $segments = explode(';', $item, 2);
            $candidate = trim($segments[0]);
            $normalized = AppLocale::toFrameworkLocale($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }
}
