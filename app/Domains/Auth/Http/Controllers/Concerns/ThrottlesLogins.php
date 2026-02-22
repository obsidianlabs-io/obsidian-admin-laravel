<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

trait ThrottlesLogins
{
    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolveLoginThrottleKey(Request $request, array $validated): string
    {
        $loginKey = trim((string) ($validated['userName'] ?? $validated['email'] ?? ''));
        if ($loginKey === '') {
            $loginKey = 'unknown';
        }

        return 'login|'.Str::lower($loginKey).'|'.$request->ip();
    }

    protected function tooManyLoginAttempts(string $throttleKey): bool
    {
        return RateLimiter::tooManyAttempts($throttleKey, $this->maxLoginAttempts());
    }

    protected function availableLoginThrottleSeconds(string $throttleKey): int
    {
        return max(1, (int) RateLimiter::availableIn($throttleKey));
    }

    protected function incrementLoginAttempts(string $throttleKey): void
    {
        RateLimiter::hit($throttleKey, $this->loginThrottleDecaySeconds());
    }

    protected function clearLoginAttempts(string $throttleKey): void
    {
        RateLimiter::clear($throttleKey);
    }

    protected function maxLoginAttempts(): int
    {
        return max(1, (int) config('auth.login_rate_limit.max_attempts', 5));
    }

    protected function loginThrottleDecaySeconds(): int
    {
        return max(1, (int) config('auth.login_rate_limit.decay_seconds', 60));
    }
}
