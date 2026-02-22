<?php

declare(strict_types=1);

return [
    'log_http_requests' => (bool) env('LOG_HTTP_REQUESTS', true),

    'tracing' => [
        'enabled' => (bool) env('OTEL_TRACE_ENABLED', true),
        'response_traceparent' => (bool) env('OTEL_RESPONSE_TRACEPARENT', true),
    ],

    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
    'idempotency_lock_timeout_seconds' => (int) env('IDEMPOTENCY_LOCK_TIMEOUT_SECONDS', 30),
    'idempotency_methods' => (static function (): array {
        $methods = explode(',', (string) env('IDEMPOTENCY_METHODS', 'POST,PUT,PATCH'));

        $normalized = array_values(array_filter(array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $methods
        )));

        return $normalized !== [] ? $normalized : ['POST'];
    })(),

    'health' => [
        'expose_checks' => (bool) env('HEALTH_EXPOSE_CHECKS', true),
    ],

    'database' => [
        'log_slow_queries' => (bool) env('DB_LOG_SLOW_QUERIES', true),
        'slow_query_threshold_ms' => (int) env('DB_SLOW_QUERY_THRESHOLD_MS', 200),
    ],

    'eloquent' => [
        'strict_mode' => (bool) env('ELOQUENT_STRICT_MODE', true),
    ],
];
