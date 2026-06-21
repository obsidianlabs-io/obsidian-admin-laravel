<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Services\IdempotencyService;
use App\Support\ApiResultCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

trait HandlesIdempotency
{
    /**
     * @param  Closure(): JsonResponse  $callback
     */
    protected function withIdempotency(Request $request, ?User $actor, Closure $callback): JsonResponse
    {
        if ((bool) $request->attributes->get('idempotency_managed', false)) {
            return $callback();
        }

        /** @var IdempotencyService $idempotencyService */
        $idempotencyService = app(IdempotencyService::class);
        $state = $idempotencyService->begin($request, $actor);

        if ($state->hasError()) {
            return $this->error(ApiResultCode::PARAM_ERROR, (string) $state->errorMessage());
        }

        if ($state->hasReplayResponse()) {
            return $state->requireReplayResponse();
        }

        $record = $state->record();

        try {
            $response = $callback();
        } catch (Throwable $exception) {
            if ($record) {
                $idempotencyService->markFailed($record);
            }

            throw $exception;
        }

        if ($record) {
            $idempotencyService->complete($record, $response);
        }

        return $response;
    }
}
