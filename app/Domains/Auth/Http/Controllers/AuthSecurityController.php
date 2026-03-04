<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\User;
use App\Http\Requests\Api\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\Auth\TwoFactorCodeRequest;
use App\Support\ApiDateTime;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class AuthSecurityController extends AbstractUserController
{
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = (string) $request->validated()['email'];
        $responseData = [];

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $token = Password::broker()->createToken($user);
            if (app()->environment('local', 'testing')) {
                $responseData['resetToken'] = $token;
            }
        }

        return $this->success($responseData, 'If the email exists, a reset link has been sent');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $passwordValidator = Validator::make($validated, [
            'password' => ['required', 'string', 'max:100', $this->strongPasswordRule()],
        ]);
        if ($passwordValidator->fails()) {
            return $this->error(self::PARAM_ERROR_CODE, $passwordValidator->errors()->first());
        }

        $status = Password::reset(
            [
                'email' => (string) $validated['email'],
                'password' => (string) $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(self::PARAM_ERROR_CODE, 'Password reset failed');
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

        $user->forceFill(['email_verified_at' => now()])->save();
        $verifiedAt = $user->getAttribute('email_verified_at');
        $this->auditLogService->record(
            action: 'user.verify_email',
            auditable: $user,
            actor: $user,
            request: $request,
            newValues: [
                'email_verified_at' => ApiDateTime::format(
                    $verifiedAt instanceof CarbonInterface ? $verifiedAt : null,
                    $this->resolveTimezone($user)
                ),
            ]
        );

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
        $otpCode = (string) $request->validated()['otpCode'];

        if (! $this->verifyUserTotpCode($user, $otpCode)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Two-factor code is invalid');
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
        $otpCode = (string) $request->validated()['otpCode'];

        if (! $this->verifyUserTotpCode($user, $otpCode)) {
            return $this->error(self::PARAM_ERROR_CODE, 'Two-factor code is invalid');
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

        $user->forceFill($payload)->save();
        $this->auditLogService->record(
            action: $action,
            auditable: $user,
            actor: $user,
            request: $request,
            newValues: ['two_factor_enabled' => $enabled]
        );
    }
}
