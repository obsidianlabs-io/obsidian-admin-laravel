<?php

declare(strict_types=1);

use App\Domains\Tenant\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

return static function (?string $version, callable $toVersionedPath): void {
    Route::prefix($toVersionedPath($version, 'tenant'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [TenantController::class, 'list'])->middleware('api.permission:tenant.view');
            Route::get('/all', [TenantController::class, 'all'])->middleware('api.permission:tenant.view');
            Route::post('', [TenantController::class, 'store'])
                ->middleware(['idempotent.request', 'api.permission:tenant.manage']);
            Route::put('/{id}', [TenantController::class, 'update'])
                ->middleware(['idempotent.request', 'api.permission:tenant.manage']);
            Route::delete('/{id}', [TenantController::class, 'destroy'])->middleware('api.permission:tenant.manage');
        });
};
