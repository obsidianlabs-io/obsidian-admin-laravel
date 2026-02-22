<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(
        Request $request,
        string $code,
        string $message,
        array $data = [],
        int $httpStatus = 200
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => $data,
            'requestId' => trim((string) ($request->attributes->get('request_id', '') ?? '')),
            'traceId' => trim((string) ($request->attributes->get('trace_id', '') ?? '')),
        ], $httpStatus);
    }
}
