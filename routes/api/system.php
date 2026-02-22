<?php

declare(strict_types=1);

use App\Domains\System\Http\Controllers\AuditLogController;
use App\Domains\System\Http\Controllers\AuditPolicyController;
use App\Domains\System\Http\Controllers\CrudSchemaController;
use App\Domains\System\Http\Controllers\FeatureFlagController;
use App\Domains\System\Http\Controllers\HealthController;
use App\Domains\System\Http\Controllers\LanguageController;
use App\Domains\System\Http\Controllers\ThemeConfigController;
use Illuminate\Support\Facades\Route;

return static function (?string $version, callable $toVersionedPath): void {
    Route::prefix($toVersionedPath($version, 'language'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [LanguageController::class, 'list'])->middleware('api.permission:language.view');
            Route::get('/options', [LanguageController::class, 'options'])->middleware('api.permission:language.view');
            Route::post('', [LanguageController::class, 'store'])
                ->middleware(['idempotent.request', 'api.permission:language.manage']);
            Route::put('/{id}', [LanguageController::class, 'update'])
                ->middleware(['idempotent.request', 'api.permission:language.manage']);
            Route::delete('/{id}', [LanguageController::class, 'destroy'])->middleware('api.permission:language.manage');
        });

    Route::prefix($toVersionedPath($version, 'audit'))
        ->middleware(['tenant.context', 'api.auth'])
        ->group(function (): void {
            Route::get('/list', [AuditLogController::class, 'list'])->middleware('api.permission:audit.view');
            Route::get('/policy/list', [AuditPolicyController::class, 'list'])->middleware('api.permission:audit.policy.view');
            Route::get('/policy/history', [AuditPolicyController::class, 'history'])->middleware('api.permission:audit.policy.view');
            Route::put('/policy', [AuditPolicyController::class, 'update'])
                ->middleware(['idempotent.request', 'api.permission:audit.policy.manage']);
        });

    Route::prefix($toVersionedPath($version, 'theme'))
        ->middleware('tenant.context')
        ->group(function (): void {
            Route::get('/public-config', [ThemeConfigController::class, 'publicShow']);

            Route::middleware('api.auth')->group(function (): void {
                Route::get('/config', [ThemeConfigController::class, 'show'])->middleware('api.permission:theme.view');
                Route::put('/config', [ThemeConfigController::class, 'update'])
                    ->middleware(['idempotent.request', 'api.permission:theme.manage']);
                Route::post('/config/reset', [ThemeConfigController::class, 'reset'])->middleware('api.permission:theme.manage');
            });
        });

    Route::get($toVersionedPath($version, 'language/locales'), [LanguageController::class, 'locales']);
    Route::get($toVersionedPath($version, 'language/messages'), [LanguageController::class, 'messages']);
    Route::get($toVersionedPath($version, 'system/bootstrap'), [LanguageController::class, 'bootstrap']);
    Route::get($toVersionedPath($version, 'health/live'), [HealthController::class, 'live']);
    Route::get($toVersionedPath($version, 'health/ready'), [HealthController::class, 'ready']);
    Route::get($toVersionedPath($version, 'health'), [HealthController::class, 'show']);

    Route::prefix($toVersionedPath($version, 'system/feature-flags'))
        ->middleware(['tenant.context', 'api.auth', 'api.permission:system.manage'])
        ->group(function (): void {
            Route::get('/', [FeatureFlagController::class, 'index']);
            Route::put('/toggle', [FeatureFlagController::class, 'toggle']);
            Route::delete('/purge', [FeatureFlagController::class, 'purge']);
        });

    Route::get($toVersionedPath($version, 'system/ui/crud-schema/{resource}'), [CrudSchemaController::class, 'show'])
        ->middleware(['tenant.context', 'api.auth']);
};
