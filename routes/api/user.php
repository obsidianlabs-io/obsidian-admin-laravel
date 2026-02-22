<?php

declare(strict_types=1);

use App\Domains\Access\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

return static function (?string $version, callable $toVersionedPath): void {
    Route::prefix($toVersionedPath($version, 'user'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [UserManagementController::class, 'listUsers'])
                ->middleware('api.permission:user.view,user.manage');
            Route::post('', [UserManagementController::class, 'createUser'])
                ->middleware(['idempotent.request', 'api.permission:user.manage']);
            Route::put('/{id}', [UserManagementController::class, 'updateUser'])
                ->middleware(['idempotent.request', 'api.permission:user.manage']);
            Route::delete('/{id}', [UserManagementController::class, 'deleteUser'])
                ->middleware('api.permission:user.manage');
            Route::put('/{id}/role', [UserManagementController::class, 'assignUserRole'])
                ->middleware(['idempotent.request', 'api.permission:user.manage']);
        });
};
