<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Domains\Shared\Services\IdempotencyService;
use App\Http\Controllers\Controller;
use App\Support\ApiDateTime;
use App\Support\ApiResponseFactory;
use App\Support\RequestTraceContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

abstract class ApiController extends Controller
{
    protected const SUCCESS_CODE = '0000';

    protected const LOGIN_FAILED_CODE = '1001';

    protected const PARAM_ERROR_CODE = '1002';

    protected const FORBIDDEN_CODE = '1003';

    protected const CONFLICT_CODE = '1009';

    protected const UNAUTHORIZED_CODE = '8888';

    protected const TOKEN_EXPIRED_CODE = '9999';

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
    protected function error(string $code, string $msg, array $data = [], int $httpStatus = 200): JsonResponse
    {
        return $this->responseFactory()->error($code, $msg, $data, $httpStatus);
    }

    /**
     * @param  Closure(): \Illuminate\Http\JsonResponse  $callback
     */
    protected function withIdempotency(Request $request, ?User $actor, Closure $callback): JsonResponse
    {
        if ((bool) $request->attributes->get('idempotency_managed', false)) {
            return $callback();
        }

        /** @var IdempotencyService $idempotencyService */
        $idempotencyService = app(IdempotencyService::class);
        $state = $idempotencyService->begin($request, $actor);

        if (isset($state['error'])) {
            return $this->error(self::PARAM_ERROR_CODE, (string) $state['error']);
        }

        if (isset($state['replayResponse'])) {
            return $state['replayResponse'];
        }

        /** @var \App\Domains\System\Models\IdempotencyKey|null $record */
        $record = $state['record'] ?? null;

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
                self::PARAM_ERROR_CODE,
                sprintf('%s version token is required', $resourceName)
            );
        }

        $parsedToken = $this->parseOptimisticLockToken($request, $token);
        if ($parsedToken === null) {
            return $this->error(
                self::PARAM_ERROR_CODE,
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
            self::CONFLICT_CODE,
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

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function hasCursorPagination(array $validated): bool
    {
        $paginationMode = strtolower(trim((string) request()->input('paginationMode', '')));
        if ($paginationMode === 'cursor') {
            return true;
        }

        if (! array_key_exists('cursor', $validated)) {
            return false;
        }

        $cursor = $validated['cursor'] ?? null;

        if (is_string($cursor)) {
            return trim($cursor) !== '';
        }

        return is_int($cursor) || is_float($cursor);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{
     *   records: \Illuminate\Database\Eloquent\Collection<int, TModel>,
     *   size: int,
     *   hasMore: bool,
     *   nextCursor: string
     * }
     */
    protected function cursorPaginateById(
        Builder $query,
        int $size,
        ?string $cursorToken = null,
        bool $descending = true
    ): array {
        $size = max(1, min(100, $size));
        $cursorId = $this->decodeCursorId($cursorToken);
        $operator = $descending ? '<' : '>';
        $direction = $descending ? 'desc' : 'asc';

        if ($cursorId !== null) {
            $query->where('id', $operator, $cursorId);
        }

        $records = $query
            ->orderBy('id', $direction)
            ->limit($size + 1)
            ->get();
        /** @var \Illuminate\Database\Eloquent\Collection<int, TModel> $records */
        $records = $records;

        $hasMore = $records->count() > $size;
        if ($hasMore) {
            $records = $records->take($size)->values();
            /** @var \Illuminate\Database\Eloquent\Collection<int, TModel> $records */
            $records = $records;
        }

        $lastModel = $records->last();
        $nextCursor = '';
        if ($hasMore && $lastModel) {
            $lastId = (int) ($lastModel->getAttribute('id') ?? 0);
            if ($lastId > 0) {
                $nextCursor = $this->encodeCursorId($lastId);
            }
        }

        return [
            'records' => $records,
            'size' => $size,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
        ];
    }

    protected function authenticate(Request $request, string $ability): ApiAuthResult
    {
        $plainToken = $request->bearerToken();
        if (! $plainToken) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        $token = PersonalAccessToken::findToken($plainToken);
        if (! $token || ! $token->can($ability)) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            $token->delete();

            return ApiAuthResult::failure(self::TOKEN_EXPIRED_CODE, 'Token expired');
        }

        $tokenable = $token->tokenable;
        if (! $tokenable instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if ($tokenable->status !== '1') {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'User is inactive');
        }

        ApiDateTime::assignRequestTimezone($request, ApiDateTime::resolveUserTimezone($tokenable));

        $now = now();
        if (! $token->last_used_at || $token->last_used_at->lt($now->copy()->subMinute())) {
            $token->forceFill(['last_used_at' => $now])->save();
        }

        return ApiAuthResult::success($tokenable, $token, self::SUCCESS_CODE, 'ok');
    }

    protected function authenticateAndAuthorize(Request $request, string $ability, string $permissionCode): ApiAuthResult
    {
        $authResult = $this->authenticate($request, $ability);

        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        if (! Gate::forUser($user)->allows('access-permission', $permissionCode)) {
            return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Forbidden');
        }

        return $authResult;
    }

    /**
     * @param  list<string>  $permissionCodes
     */
    protected function authenticateAndAuthorizeAny(Request $request, string $ability, array $permissionCodes): ApiAuthResult
    {
        $authResult = $this->authenticate($request, $ability);

        if ($authResult->failed()) {
            return $authResult;
        }

        $user = $authResult->user();
        if (! $user instanceof User) {
            return ApiAuthResult::failure(self::UNAUTHORIZED_CODE, 'Unauthorized');
        }

        foreach ($permissionCodes as $permissionCode) {
            if (Gate::forUser($user)->allows('access-permission', $permissionCode)) {
                return $authResult;
            }
        }

        return ApiAuthResult::failure(self::FORBIDDEN_CODE, 'Forbidden');
    }

    protected function isSuperAdmin(User $user): bool
    {
        $user->loadMissing('role:id,code,level,status');

        $roleCode = '';
        if ($user->role instanceof Role) {
            $roleCode = (string) $user->role->code;
        }

        return $roleCode === 'R_SUPER';
    }

    protected function requestId(): string
    {
        return RequestTraceContext::requestId();
    }

    protected function traceId(): string
    {
        return RequestTraceContext::traceId();
    }

    protected function responseFactory(): ApiResponseFactory
    {
        /** @var ApiResponseFactory $factory */
        $factory = app(ApiResponseFactory::class);

        return $factory;
    }

    private function decodeCursorId(?string $token): ?int
    {
        $raw = trim((string) $token);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            $value = (int) $raw;

            return $value > 0 ? $value : null;
        }

        $normalized = strtr($raw, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', $decoded)) {
            return null;
        }

        $value = (int) $decoded;

        return $value > 0 ? $value : null;
    }

    private function encodeCursorId(int $id): string
    {
        return rtrim(strtr(base64_encode((string) max(1, $id)), '+/', '-_'), '=');
    }
}
