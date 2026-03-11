<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureApiPermission;
use App\Http\Middleware\HandleIdempotentRequests;
use App\Http\Middleware\RecordApiAccessLog;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\SetRequestLocale;
use App\Support\ApiErrorResponse;
use App\Support\TrustedProxyConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = (string) Env::get('TRUSTED_PROXIES', 'REMOTE_ADDR');
        $trustedProxyHeadersConfig = (string) Env::get('TRUSTED_PROXY_HEADERS', 'DEFAULT');
        $trustedProxyHeaders = TrustedProxyConfig::parseHeadersMask(
            $trustedProxyHeadersConfig
        ) ?? TrustedProxyConfig::defaultHeadersMask();

        if (is_string($trustedProxies) && trim($trustedProxies) !== '') {
            $middleware->trustProxies($trustedProxies, $trustedProxyHeaders);
        }

        $middleware->api(
            prepend: [
                AssignRequestId::class,
                SetRequestLocale::class,
            ],
            append: [
                RecordApiAccessLog::class,
            ]
        );

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'request.locale' => SetRequestLocale::class,
            'request.id' => AssignRequestId::class,
            'idempotent.request' => HandleIdempotentRequests::class,
            'api.auth' => AuthenticateApiToken::class,
            'api.permission' => EnsureApiPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            NotFoundHttpException::class,
            MethodNotAllowedHttpException::class,
            ModelNotFoundException::class,
            ThrottleRequestsException::class,
        ]);

        $isApiRequest = static fn (Request $request): bool => $request->is('api/*');

        $exceptions->render(function (HttpResponseException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            return $exception->getResponse();
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = trim((string) ($exception->validator?->errors()->first() ?? $exception->getMessage()));
            if ($message === '') {
                $message = 'Validation failed';
            }

            return ApiErrorResponse::json($request, '1002', $message);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = trim($exception->getMessage());
            if ($message === '') {
                $message = 'Unauthorized';
            }

            return ApiErrorResponse::json($request, '8888', $message);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = trim($exception->getMessage());
            if ($message === '') {
                $message = 'Forbidden';
            }

            return ApiErrorResponse::json($request, '1003', $message);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = trim($exception->getMessage());
            if ($message === '') {
                $message = 'Too many requests';
            }

            $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 0);
            $data = $retryAfter > 0 ? ['retryAfter' => $retryAfter] : [];

            return ApiErrorResponse::json($request, '4290', $message, $data);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            unset($exception);

            return ApiErrorResponse::json($request, '4040', 'Not Found');
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            unset($exception);

            return ApiErrorResponse::json($request, '4040', 'Not Found');
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            unset($exception);

            return ApiErrorResponse::json($request, '4050', 'Method Not Allowed');
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isApiRequest) {
            if (! $isApiRequest($request)) {
                return null;
            }

            $message = 'Server error';
            if ((bool) config('app.debug', false)) {
                $debugMessage = trim((string) $exception->getMessage());
                if ($debugMessage !== '') {
                    $message = $debugMessage;
                }
            }

            return ApiErrorResponse::json($request, '5000', $message);
        });
    })->create();
