<?php

declare(strict_types=1);

namespace App\Domains\System\Http\Controllers;

use App\Domains\Shared\Http\Controllers\ApiController;
use App\Domains\System\Services\HealthStatusService;
use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function __construct(private readonly HealthStatusService $healthStatusService) {}

    public function live(): JsonResponse
    {
        return response()->json($this->responseFactory()->withTrace([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    public function ready(): JsonResponse
    {
        $snapshot = $this->healthStatusService->snapshot();
        $exposeChecks = (bool) config('observability.health.expose_checks', true);
        $isReady = $snapshot->isReady();

        return response()->json($this->responseFactory()->withTrace([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => $isReady ? 'ready' : 'not_ready',
            'ready' => $isReady,
            'timestamp' => now()->toIso8601String(),
            'context' => $snapshot->context->toArray(),
            'checks' => $exposeChecks ? $snapshot->checksToArray() : [],
        ]), $isReady ? 200 : 503);
    }

    public function show(): JsonResponse
    {
        $snapshot = $this->healthStatusService->snapshot();
        $exposeChecks = (bool) config('observability.health.expose_checks', true);

        return response()->json($this->responseFactory()->withTrace([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => $snapshot->status,
            'timestamp' => now()->toIso8601String(),
            'context' => $snapshot->context->toArray(),
            'checks' => $exposeChecks ? $snapshot->checksToArray() : [],
        ]));
    }
}
