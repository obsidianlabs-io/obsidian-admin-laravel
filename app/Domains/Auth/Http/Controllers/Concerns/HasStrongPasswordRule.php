<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Concerns;

use Illuminate\Validation\Rules\Password as PasswordRule;

trait HasStrongPasswordRule
{
    protected function strongPasswordRule(): PasswordRule
    {
        $rule = PasswordRule::min(max(8, (int) config('auth.password_policy.min', 8)))
            ->letters()
            ->numbers();

        if ((bool) config('auth.password_policy.require_mixed_case', true)) {
            $rule = $rule->mixedCase();
        }

        if ((bool) config('auth.password_policy.require_symbols', false)) {
            $rule = $rule->symbols();
        }

        if ((bool) config('auth.password_policy.check_uncompromised', false)) {
            $rule = $rule->uncompromised();
        }

        return $rule;
    }
}
