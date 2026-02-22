<?php

declare(strict_types=1);

use App\Domains\Access\Http\Controllers\PermissionController;
use App\Domains\Access\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

return static function (?string $version, callable $toVersionedPath): void {
    Route::prefix($toVersionedPath($version, 'role'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [RoleController::class, 'list'])->middleware('api.permission:role.view');
            Route::get('/all', [RoleController::class, 'all'])->middleware('api.permission:role.view,user.view,user.manage');
            Route::get('/assignable-permissions', [RoleController::class, 'assignablePermissions'])
                ->middleware('api.permission:role.view,role.manage');
            Route::post('', [RoleController::class, 'store'])
                ->middleware(['idempotent.request', 'api.permission:role.manage']);
            Route::put('/{id}', [RoleController::class, 'update'])
                ->middleware(['idempotent.request', 'api.permission:role.manage']);
            Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('api.permission:role.manage');
            Route::put('/{id}/permissions', [RoleController::class, 'syncPermissions'])
                ->middleware(['idempotent.request', 'api.permission:role.manage']);
        });

    Route::prefix($toVersionedPath($version, 'permission'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [PermissionController::class, 'list'])->middleware('api.permission:permission.view');
            Route::get('/all', [PermissionController::class, 'all'])->middleware('api.permission:permission.view,role.manage');
            Route::post('', [PermissionController::class, 'store'])
                ->middleware(['idempotent.request', 'api.permission:permission.manage']);
            Route::put('/{id}', [PermissionController::class, 'update'])
                ->middleware(['idempotent.request', 'api.permission:permission.manage']);
            Route::delete('/{id}', [PermissionController::class, 'destroy'])->middleware('api.permission:permission.manage');
        });
};
