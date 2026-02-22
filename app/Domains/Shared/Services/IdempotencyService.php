<?php

declare(strict_types=1);

namespace App\Domains\Shared\Services;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\IdempotencyKey;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyService
{
    private const HEADER_NAME = 'Idempotency-Key';

    /**
     * @return array{
     *   enabled: bool,
     *   error?: string,
     *   replayResponse?: \Illuminate\Http\JsonResponse,
     *   record?: \App\Domains\System\Models\IdempotencyKey
     * }
     */
    public function begin(Request $request, ?User $user): array
    {
        $idempotencyKey = trim((string) $request->header(self::HEADER_NAME, ''));
        if ($idempotencyKey === '') {
            return ['enabled' => false];
        }

        if (strlen($idempotencyKey) > 128) {
            return [
                'enabled' => true,
                'error' => self::HEADER_NAME.' is too long',
            ];
        }

        $actorKey = $this->resolveActorKey($request, $user);
        $method = strtoupper($request->method());
        $routePath = trim((string) ($request->route()?->uri() ?? $request->path()), '/');
        $requestHash = $this->payloadHash($request->all());
        $createdNow = false;

        $record = IdempotencyKey::query()
            ->where('actor_key', $actorKey)
            ->where('method', $method)
            ->where('route_path', $routePath)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($record) {
            $expiresAt = $record->getAttribute('expires_at');
            if ($expiresAt instanceof CarbonInterface && $expiresAt->isPast()) {
                $record->delete();
                $record = null;
            }
        }

        if (! $record) {
            try {
                $record = IdempotencyKey::query()->create([
                    'actor_key' => $actorKey,
                    'user_id' => $user?->id,
                    'method' => $method,
                    'route_path' => $routePath,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'status' => 'processing',
                ]);
                $createdNow = true;
            } catch (QueryException) {
                $record = IdempotencyKey::query()
                    ->where('actor_key', $actorKey)
                    ->where('method', $method)
                    ->where('route_path', $routePath)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
            }
        }

        if (! $record) {
            return [
                'enabled' => true,
                'error' => 'Unable to acquire idempotency key lock',
            ];
        }

        if (! hash_equals((string) $record->request_hash, $requestHash)) {
            return [
                'enabled' => true,
                'error' => 'Idempotency-Key has been used with a different request payload',
            ];
        }

        if ($record->status === 'completed' && $this->extractPayload($record) !== null) {
            return [
                'enabled' => true,
                'replayResponse' => $this->buildReplayResponse($record),
            ];
        }

        if (! $createdNow && $record->status === 'processing') {
            $lockTimeoutSeconds = max(5, (int) config('observability.idempotency_lock_timeout_seconds', 30));
            if ($record->updated_at && $record->updated_at->gt(now()->subSeconds($lockTimeoutSeconds))) {
                return [
                    'enabled' => true,
                    'error' => 'Request with this Idempotency-Key is already being processed',
                ];
            }
        }

        $record->forceFill([
            'status' => 'processing',
            'response_payload' => null,
            'http_status' => null,
            'expires_at' => null,
        ])->save();

        return [
            'enabled' => true,
            'record' => $record,
        ];
    }

    public function complete(IdempotencyKey $record, JsonResponse $response): void
    {
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            $payload = [
                'code' => '0000',
                'msg' => 'ok',
                'data' => [],
            ];
        }

        $ttlHours = max(1, (int) config('observability.idempotency_ttl_hours', 24));

        $record->forceFill([
            'status' => 'completed',
            'response_payload' => $payload,
            'http_status' => $response->getStatusCode(),
            'expires_at' => now()->addHours($ttlHours),
        ])->save();
    }

    public function markFailed(IdempotencyKey $record): void
    {
        $record->forceFill([
            'status' => 'failed',
        ])->save();
    }

    private function resolveActorKey(Request $request, ?User $user): string
    {
        if ($user?->id) {
            return 'user:'.$user->id;
        }

        $ip = trim((string) $request->ip());
        $agent = trim((string) $request->userAgent());

        return sprintf('guest:%s:%s', $ip !== '' ? $ip : 'unknown', substr(hash('sha256', $agent), 0, 24));
    }

    private function payloadHash(mixed $payload): string
    {
        return hash('sha256', json_encode($this->normalizePayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function normalizePayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        if (array_is_list($payload)) {
            return array_map(fn (mixed $item): mixed => $this->normalizePayload($item), $payload);
        }

        ksort($payload);
        foreach ($payload as $key => $value) {
            $payload[$key] = $this->normalizePayload($value);
        }

        return $payload;
    }

    private function buildReplayResponse(IdempotencyKey $record): JsonResponse
    {
        $statusCode = max(200, (int) ($record->http_status ?? 200));
        $payload = $this->extractPayload($record) ?? [
            'code' => '0000',
            'msg' => 'ok',
            'data' => [],
        ];
        $requestId = trim((string) (request()->attributes->get('request_id', '') ?? ''));
        if ($requestId !== '') {
            $payload['requestId'] = $requestId;
        }
        $traceId = trim((string) (request()->attributes->get('trace_id', '') ?? ''));
        if ($traceId !== '') {
            $payload['traceId'] = $traceId;
        }

        return response()
            ->json($payload, $statusCode)
            ->header('X-Idempotent-Replay', '1');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractPayload(IdempotencyKey $record): ?array
    {
        $payload = $record->getAttribute('response_payload');
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
