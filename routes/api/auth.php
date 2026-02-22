<?php

declare(strict_types=1);

use App\Domains\Auth\Http\Controllers\AuthSecurityController;
use App\Domains\Auth\Http\Controllers\AuthSessionController;
use App\Domains\Auth\Http\Controllers\UserProfileController;
use Illuminate\Support\Facades\Route;

return static function (?string $version, callable $toVersionedPath): void {
    Route::prefix($toVersionedPath($version, 'auth'))
        ->middleware('tenant.context')
        ->group(function (): void {
            Route::post('/register', [AuthSessionController::class, 'register'])->middleware('idempotent.request');
            Route::post('/login', [AuthSessionController::class, 'login']);
            Route::post('/forgot-password', [AuthSecurityController::class, 'forgotPassword']);
            Route::post('/reset-password', [AuthSecurityController::class, 'resetPassword']);
            Route::post('/refreshToken', [AuthSessionController::class, 'refreshToken']);

            Route::middleware('api.auth')->group(function (): void {
                Route::get('/getUserInfo', [UserProfileController::class, 'getUserInfo']);
                Route::get('/menus', [UserProfileController::class, 'menus']);
                Route::get('/profile', [UserProfileController::class, 'getProfile']);
                Route::put('/profile', [UserProfileController::class, 'updateProfile'])->middleware('idempotent.request');
                Route::put('/preferred-locale', [UserProfileController::class, 'updatePreferredLocale'])->middleware('idempotent.request');
                Route::put('/preferences', [UserProfileController::class, 'updateUserPreferences'])->middleware('idempotent.request');
                Route::get('/timezones', [UserProfileController::class, 'timezones']);
                Route::post('/logout', [AuthSessionController::class, 'logout']);
                Route::get('/sessions', [AuthSessionController::class, 'sessions']);
                Route::put('/sessions/{sessionId}/alias', [AuthSessionController::class, 'updateSessionAlias'])->middleware('idempotent.request');
                Route::delete('/sessions/{sessionId}', [AuthSessionController::class, 'revokeSession']);
                Route::get('/me', [UserProfileController::class, 'me']);
                Route::post('/verify-email', [AuthSecurityController::class, 'verifyEmail']);
                Route::post('/2fa/setup', [AuthSecurityController::class, 'setupTwoFactor']);
                Route::post('/2fa/enable', [AuthSecurityController::class, 'enableTwoFactor']);
                Route::post('/2fa/disable', [AuthSecurityController::class, 'disableTwoFactor']);
                Route::get('/error', [AuthSessionController::class, 'customError']);
            });
        });
};
