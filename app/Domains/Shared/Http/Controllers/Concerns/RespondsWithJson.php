<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Support\ApiResponseFactory;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;

trait RespondsWithJson
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function success(array $data = [], string $msg = 'ok'): JsonResponse
    {
        return $this->responseFactory()->success($data, $msg);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function error(string|ApiResultCode $code, string $msg, array $data = [], int $httpStatus = 200): JsonResponse
    {
        $codeValue = $code instanceof ApiResultCode ? $code->value : $code;

        if ($httpStatus === 200) {
            $httpStatus = $code instanceof ApiResultCode ? $code->httpStatus() : ApiResultCode::resolveHttpStatus($codeValue);
        }

        return $this->responseFactory()->error($codeValue, $msg, $data, $httpStatus);
    }

    protected function responseFactory(): ApiResponseFactory
    {
        /** @var ApiResponseFactory $factory */
        $factory = app(ApiResponseFactory::class);

        return $factory;
    }
}
