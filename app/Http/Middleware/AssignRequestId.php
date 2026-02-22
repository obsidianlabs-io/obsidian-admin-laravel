<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Clear any lingering shared logger context from a prior Octane request.
        Log::withoutContext();

        $requestId = $this->resolveRequestId($request);
        $traceContext = $this->resolveTraceContext($request);
        $startedAt = microtime(true);

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceContext['traceId']);
        $request->attributes->set('traceparent', $traceContext['traceParent']);
        $request->attributes->set('parent_span_id', $traceContext['parentSpanId']);
        $request->attributes->set('span_id', $traceContext['spanId']);
        Log::withContext([
            'request_id' => $requestId,
            'trace_id' => $traceContext['traceId'],
            'span_id' => $traceContext['spanId'],
            'parent_span_id' => $traceContext['parentSpanId'],
            'http_method' => $request->method(),
            'http_path' => '/'.$request->path(),
            'client_ip' => $request->ip(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        if ($traceContext['traceId'] !== '') {
            $response->headers->set('X-Trace-Id', $traceContext['traceId']);
        }

        if (
            $traceContext['traceParent'] !== ''
            && (bool) config('observability.tracing.response_traceparent', true)
        ) {
            $response->headers->set('traceparent', $traceContext['traceParent']);
        }

        if ((bool) config('observability.log_http_requests', true)) {
            Log::info('http.request.completed', [
                'request_id' => $requestId,
                'trace_id' => $traceContext['traceId'],
                'span_id' => $traceContext['spanId'],
                'http_method' => $request->method(),
                'http_path' => '/'.$request->path(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ]);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incomingRequestId = trim((string) $request->header('X-Request-Id', ''));
        if ($incomingRequestId !== '' && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $incomingRequestId) === 1) {
            return $incomingRequestId;
        }

        return (string) Str::uuid();
    }

    /**
     * @return array{
     *   traceId: string,
     *   spanId: string,
     *   parentSpanId: string,
     *   traceParent: string
     * }
     */
    private function resolveTraceContext(Request $request): array
    {
        if (! (bool) config('observability.tracing.enabled', true)) {
            return [
                'traceId' => '',
                'spanId' => '',
                'parentSpanId' => '',
                'traceParent' => '',
            ];
        }

        $incoming = trim((string) $request->header('traceparent', ''));
        $traceId = '';
        $parentSpanId = '';
        $traceFlags = '01';

        if (
            preg_match(
                '/^[\da-f]{2}-([\da-f]{32})-([\da-f]{16})-([\da-f]{2})$/i',
                $incoming,
                $matches
            ) === 1
        ) {
            $traceId = strtolower((string) $matches[1]);
            $parentSpanId = strtolower((string) $matches[2]);
            $traceFlags = strtolower((string) $matches[3]);
        }

        if ($traceId === '') {
            $traceId = $this->randomHex(32);
        }

        $spanId = $this->randomHex(16);
        $traceParent = sprintf('00-%s-%s-%s', $traceId, $spanId, $traceFlags);

        return [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'parentSpanId' => $parentSpanId,
            'traceParent' => $traceParent,
        ];
    }

    private function randomHex(int $length): string
    {
        $byteLength = max(1, (int) ceil($length / 2));

        return substr(bin2hex(random_bytes($byteLength)), 0, $length);
    }
}
