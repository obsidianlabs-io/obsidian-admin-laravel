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
        return response()->json([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
            'requestId' => $this->requestIdPayload(),
            'traceId' => $this->traceIdPayload(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $snapshot = $this->healthStatusService->snapshot();
        $exposeChecks = (bool) config('observability.health.expose_checks', true);
        $isReady = $snapshot['status'] !== 'fail';

        return response()->json([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => $isReady ? 'ready' : 'not_ready',
            'ready' => $isReady,
            'timestamp' => now()->toIso8601String(),
            'requestId' => $this->requestIdPayload(),
            'traceId' => $this->traceIdPayload(),
            'context' => $snapshot['context'],
            'checks' => $exposeChecks ? $snapshot['checks'] : [],
        ], $isReady ? 200 : 503);
    }

    public function show(): JsonResponse
    {
        $snapshot = $this->healthStatusService->snapshot();
        $exposeChecks = (bool) config('observability.health.expose_checks', true);

        return response()->json([
            'name' => config('app.name'),
            'service' => 'obsidian-admin-laravel',
            'status' => $snapshot['status'],
            'timestamp' => now()->toIso8601String(),
            'requestId' => $this->requestIdPayload(),
            'traceId' => $this->traceIdPayload(),
            'context' => $snapshot['context'],
            'checks' => $exposeChecks ? $snapshot['checks'] : [],
        ]);
    }

    private function requestIdPayload(): string
    {
        return trim((string) (request()->attributes->get('request_id', '') ?? ''));
    }

    private function traceIdPayload(): string
    {
        return trim((string) (request()->attributes->get('trace_id', '') ?? ''));
    }
}
