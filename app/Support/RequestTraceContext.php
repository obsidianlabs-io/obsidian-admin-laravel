<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

final class RequestTraceContext
{
    /**
     * @return array{requestId: string, traceId: string}
     */
    public static function payload(?Request $request = null): array
    {
        return [
            'requestId' => self::requestId($request),
            'traceId' => self::traceId($request),
        ];
    }

    public static function requestId(?Request $request = null): string
    {
        $request ??= request();

        return trim((string) ($request->attributes->get('request_id', '') ?? ''));
    }

    public static function traceId(?Request $request = null): string
    {
        $request ??= request();

        return trim((string) ($request->attributes->get('trace_id', '') ?? ''));
    }
}
