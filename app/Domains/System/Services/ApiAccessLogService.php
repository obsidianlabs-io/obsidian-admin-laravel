<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\System\Data\ApiAccessLogPruneResultData;
use App\Domains\System\Models\ApiAccessLog;
use App\Support\RequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessLogService
{
    public function record(Request $request, Response $response, int $durationMs): void
    {
        if (! $this->shouldRecord($request, $response)) {
            return;
        }

        $routeName = '';
        $route = $request->route();
        if (is_object($route)) {
            $routeName = trim((string) ($route->getName() ?? ''));
        }

        $requestId = trim((string) ($request->attributes->get('request_id', '') ?? ''));
        $traceId = trim((string) ($request->attributes->get('trace_id', '') ?? ''));
        $path = trim($request->path(), '/');
        $method = strtoupper(trim($request->method()));

        ApiAccessLog::query()->create([
            'request_id' => $requestId !== '' ? $requestId : null,
            'trace_id' => $traceId !== '' ? $traceId : null,
            'user_id' => $this->resolveUserId($request),
            'tenant_id' => $this->resolveTenantId($request),
            'method' => $method !== '' ? $method : 'GET',
            'path' => $path,
            'route_name' => $routeName !== '' ? $routeName : null,
            'status_code' => max(100, min(599, (int) $response->getStatusCode())),
            'duration_ms' => max(0, $durationMs),
            'request_size' => $this->resolveRequestSize($request),
            'response_size' => $this->resolveResponseSize($response),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function pruneExpiredLogs(bool $dryRun = false): ApiAccessLogPruneResultData
    {
        $retentionDays = $this->retentionDays();
        $cutoff = now()->subDays($retentionDays);
        $query = ApiAccessLog::query()->where('created_at', '<', $cutoff);

        $totalDeleted = $dryRun ? (int) $query->count() : (int) $query->delete();

        return new ApiAccessLogPruneResultData(
            dryRun: $dryRun,
            retentionDays: $retentionDays,
            totalDeleted: $totalDeleted,
        );
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! (bool) config('audit.api_access.enabled', false)) {
            return false;
        }

        $path = trim($request->path(), '/');
        if ($path === '' || ! Str::startsWith($path, 'api/')) {
            return false;
        }

        if ($this->isExcludedPath($path)) {
            return false;
        }

        $statusCode = (int) $response->getStatusCode();
        if ($statusCode >= 500) {
            return true;
        }

        if ((bool) config('audit.api_access.errors_only', false)) {
            return $statusCode >= 400;
        }

        $sampleRate = $this->normalizeSampleRate(config('audit.api_access.sample_rate', 0.2));
        if ($sampleRate >= 1.0) {
            return true;
        }
        if ($sampleRate <= 0.0) {
            return false;
        }

        $threshold = (int) floor($sampleRate * 10000);

        return random_int(1, 10000) <= max(0, min(10000, $threshold));
    }

    private function isExcludedPath(string $path): bool
    {
        $excludedPaths = config('audit.api_access.excluded_paths', []);
        if (! is_array($excludedPaths)) {
            return false;
        }

        foreach ($excludedPaths as $pattern) {
            $normalizedPattern = trim((string) $pattern);
            if ($normalizedPattern === '') {
                continue;
            }

            if (Str::is($normalizedPattern, $path)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUserId(Request $request): ?int
    {
        $authUser = $request->attributes->get('auth_user');
        if (is_object($authUser) && isset($authUser->id) && is_numeric($authUser->id)) {
            return (int) $authUser->id;
        }

        return RequestContext::userId();
    }

    private function resolveTenantId(Request $request): ?int
    {
        $tenantContext = $request->attributes->get('tenant_context');
        if (is_array($tenantContext) && is_numeric($tenantContext['tenantId'] ?? null)) {
            return (int) $tenantContext['tenantId'];
        }

        return RequestContext::tenantId();
    }

    private function resolveRequestSize(Request $request): ?int
    {
        $headerValue = trim((string) $request->header('Content-Length', ''));
        if (is_numeric($headerValue)) {
            return max(0, (int) $headerValue);
        }

        return null;
    }

    private function resolveResponseSize(Response $response): ?int
    {
        $contentLength = trim((string) ($response->headers->get('Content-Length') ?? ''));
        if (is_numeric($contentLength)) {
            return max(0, (int) $contentLength);
        }

        $content = $response->getContent();
        if (! is_string($content)) {
            return null;
        }

        return strlen($content);
    }

    private function normalizeSampleRate(mixed $rate): float
    {
        $value = (float) $rate;
        if ($value < 0) {
            $value = 0.0;
        } elseif ($value > 1) {
            $value = 1.0;
        }

        return round($value, 4);
    }

    private function retentionDays(): int
    {
        $days = (int) config('audit.api_access.retention_days', 30);

        if ($days < 1) {
            return 1;
        }
        if ($days > 3650) {
            return 3650;
        }

        return $days;
    }
}
