<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Support\ApiDateTime;
use App\Support\ApiResultCode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

trait HandlesOptimisticLocks
{
    /**
     * @param  list<string>|null  $tokenFields
     */
    protected function ensureOptimisticLock(
        Request $request,
        Model $model,
        string $resourceName = 'Resource',
        ?array $tokenFields = null
    ): ?JsonResponse {
        $token = $this->resolveOptimisticLockToken($request, $tokenFields);
        $tokenRequired = (bool) config('security.optimistic_lock.require_token', false);

        if ($token === '') {
            if (! $tokenRequired) {
                return null;
            }

            return $this->error(
                ApiResultCode::PARAM_ERROR,
                sprintf('%s version token is required', $resourceName)
            );
        }

        $parsedToken = $this->parseOptimisticLockToken($request, $token);
        if ($parsedToken === null) {
            return $this->error(
                ApiResultCode::PARAM_ERROR,
                sprintf('%s version token is invalid', $resourceName)
            );
        }

        $updatedAt = $model->getAttribute('updated_at');
        if (! $updatedAt instanceof CarbonInterface) {
            return null;
        }

        $currentVersion = $updatedAt->copy()->setTimezone('UTC')->timestamp;
        if ($parsedToken === $currentVersion) {
            return null;
        }

        return $this->error(
            ApiResultCode::CONFLICT,
            sprintf('%s has been modified by another user. Please refresh and retry.', $resourceName),
            [
                'currentVersion' => (string) $currentVersion,
                'currentUpdatedAt' => ApiDateTime::formatForRequest($updatedAt, $request),
            ]
        );
    }

    /**
     * @param  list<string>|null  $tokenFields
     */
    private function resolveOptimisticLockToken(Request $request, ?array $tokenFields = null): string
    {
        $fields = $tokenFields ?? (array) config('security.optimistic_lock.token_fields', ['version']);

        foreach ($fields as $field) {
            $name = trim((string) $field);
            if ($name === '') {
                continue;
            }

            $value = $request->input($name);

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $headerValue = trim((string) $request->header('If-Unmodified-Since', ''));

        return $headerValue;
    }

    private function parseOptimisticLockToken(Request $request, string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        try {
            $timezone = ApiDateTime::requestTimezone($request);

            return (int) CarbonImmutable::parse($value, $timezone)
                ->setTimezone('UTC')
                ->timestamp;
        } catch (Throwable) {
            return null;
        }
    }
}
