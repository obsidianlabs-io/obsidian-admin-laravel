<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\TwoFactorCodeRequest;
use App\Support\ApiDateTime;
use App\Support\ApiResultCode;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AuthSecurityController extends AbstractUserController
{
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->email();
        $responseData = [];

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $token = Password::broker()->createToken($user);
            // Only expose reset token in non-production environments AND when explicitly enabled via config.
            // This prevents accidental token leakage if APP_ENV is misconfigured in production.
            if (app()->environment('local', 'testing') && (bool) config('security.expose_password_reset_tokens', false)) {
                $responseData['resetToken'] = $token;
            }
        }

        return $this->success($responseData, 'If the email exists, a reset link has been sent');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $input = $request->toDTO();

        $status = Password::reset(
            $input->toBrokerPayload(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Password reset failed');
        }

        return $this->success([], 'Password has been reset');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        if ($user->email_verified_at !== null) {
            return $this->success([], 'Email already verified');
        }

        DB::transaction(function () use ($user) {
            $user->forceFill(['email_verified_at' => now()])->save();
        });

        $verifiedAt = $user->getAttribute('email_verified_at');
        $timezone = $this->resolveTimezone($user);

        DB::afterCommit(static function () use ($user, $verifiedAt, $timezone) {
            event(DomainAuditEvent::make(
                action: 'user.verify_email',
                auditable: $user,
                actor: $user,
                newValues: [
                    'email_verified_at' => ApiDateTime::format(
                        $verifiedAt instanceof CarbonInterface ? $verifiedAt : null,
                        $timezone
                    ),
                ]
            ));
        });

        return $this->success([], 'Email verified');
    }

    public function setupTwoFactor(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $secret = $this->totpService->generateSecret();
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => false,
        ])->save();

        $appName = (string) config('app.name', 'ObsidianAdmin');
        $otpauthUrl = $this->totpService->otpauthUrl($appName, (string) $user->email, $secret);

        return $this->success([
            'secret' => $secret,
            'otpauthUrl' => $otpauthUrl,
            'enabled' => false,
        ], 'Two-factor secret generated');
    }

    public function enableTwoFactor(TwoFactorCodeRequest $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $otpCode = $request->otpCode();

        if (! $this->verifyUserTotpCode($user, $otpCode)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Two-factor code is invalid');
        }

        $this->applyTwoFactorState(
            user: $user,
            request: $request,
            enabled: true,
            clearSecret: false,
            action: 'user.2fa.enable'
        );

        return $this->success(['enabled' => true], 'Two-factor enabled');
    }

    public function disableTwoFactor(TwoFactorCodeRequest $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $otpCode = $request->otpCode();

        if (! $this->verifyUserTotpCode($user, $otpCode)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Two-factor code is invalid');
        }

        $this->applyTwoFactorState(
            user: $user,
            request: $request,
            enabled: false,
            clearSecret: true,
            action: 'user.2fa.disable'
        );

        return $this->success(['enabled' => false], 'Two-factor disabled');
    }

    private function applyTwoFactorState(
        User $user,
        Request $request,
        bool $enabled,
        bool $clearSecret,
        string $action
    ): void {
        $payload = ['two_factor_enabled' => $enabled];
        if ($clearSecret) {
            $payload['two_factor_secret'] = null;
        }

        DB::transaction(function () use ($user, $payload) {
            $user->forceFill($payload)->save();
        });

        DB::afterCommit(static function () use ($user, $action, $enabled) {
            event(DomainAuditEvent::make(
                action: $action,
                auditable: $user,
                actor: $user,
                newValues: ['two_factor_enabled' => $enabled]
            ));
        });
    }
}
