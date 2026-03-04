<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponseFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function success(
        array $data = [],
        string $message = 'ok',
        int $httpStatus = 200,
        ?Request $request = null
    ): JsonResponse {
        return response()->json([
            'code' => '0000',
            'msg' => $message,
            'data' => $data,
            ...RequestTraceContext::payload($request),
        ], $httpStatus);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function error(
        string $code,
        string $message,
        array $data = [],
        int $httpStatus = 200,
        ?Request $request = null
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'msg' => $message,
            'data' => $data,
            ...RequestTraceContext::payload($request),
        ], $httpStatus);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withTrace(array $payload, ?Request $request = null): array
    {
        return [
            ...$payload,
            ...RequestTraceContext::payload($request),
        ];
    }
}
