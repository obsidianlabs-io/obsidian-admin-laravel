<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\TotpService;
use Illuminate\Support\Facades\Crypt;

/**
 * @property-read TotpService $totpService
 */
trait VerifiesTotpCode
{
    protected function verifyUserTotpCode(User $user, string $code): bool
    {
        $encryptedSecret = (string) ($user->two_factor_secret ?? '');
        if ($encryptedSecret === '') {
            return false;
        }

        try {
            $secret = Crypt::decryptString($encryptedSecret);
        } catch (\Throwable) {
            return false;
        }

        return $this->totpService->verifyOnce(
            $secret,
            $code,
            'user:'.$user->id,
            max(0, (int) config('security.totp_window', 1))
        );
    }
}
