<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Services\IdempotencyService;
use App\Domains\System\Models\IdempotencyKey;
use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HandleIdempotentRequests
{
    public function __construct(private readonly IdempotencyService $idempotencyService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $method = strtoupper($request->method());
        $supportedMethods = collect((array) config('observability.idempotency_methods', ['POST']))
            ->map(static fn (mixed $item): string => strtoupper(trim((string) $item)))
            ->filter(static fn (string $item): bool => $item !== '')
            ->values()
            ->all();

        if (! in_array($method, $supportedMethods, true)) {
            return $next($request);
        }

        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return $next($request);
        }

        $user = $this->resolveActor($request);
        $state = $this->idempotencyService->begin($request, $user);

        if (isset($state['error'])) {
            return ApiErrorResponse::json(
                $request,
                '1002',
                (string) $state['error'],
                [],
                409
            );
        }

        if (isset($state['replayResponse'])) {
            return $state['replayResponse'];
        }

        $record = $state['record'] ?? null;
        if (! $record instanceof IdempotencyKey) {
            return $next($request);
        }

        $request->attributes->set('idempotency_managed', true);
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->idempotencyService->markFailed($record);

            throw $exception;
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            $this->idempotencyService->complete($record, $response);

            return $response;
        }

        $this->idempotencyService->markFailed($record);

        return $response;
    }

    private function resolveActor(Request $request): ?User
    {
        $actor = $request->attributes->get('auth_user');
        if ($actor instanceof User) {
            return $actor;
        }

        $token = $request->bearerToken();
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $personalAccessToken = PersonalAccessToken::findToken($token);
        $tokenable = $personalAccessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
