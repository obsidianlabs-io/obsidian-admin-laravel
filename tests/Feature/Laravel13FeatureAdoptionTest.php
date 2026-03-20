<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\System\Listeners\RecordAsyncAuditEvent;
use App\Http\Middleware\CacheOpenApiSpec;
use App\Jobs\WriteAuditLogJob;
use DateTimeInterface;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class Laravel13FeatureAdoptionTest extends TestCase
{
    public function test_sanctum_uses_prevent_request_forgery_middleware(): void
    {
        $this->assertSame(
            PreventRequestForgery::class,
            config('sanctum.middleware.validate_csrf_token')
        );
    }

    public function test_audit_queue_routes_are_registered_centrally(): void
    {
        /** @var array<class-string, array{0:string|null, 1:string|null}> $routes */
        $routes = app('queue.routes')->all();

        $expectedConnection = trim((string) config('audit.queue.connection', (string) config('queue.default', 'database')));
        $expectedQueue = trim((string) config('audit.queue.name', 'audit'));

        $this->assertSame(
            [$expectedConnection !== '' ? $expectedConnection : null, $expectedQueue !== '' ? $expectedQueue : null],
            $routes[WriteAuditLogJob::class] ?? null
        );
        $this->assertSame(
            [$expectedConnection !== '' ? $expectedConnection : null, $expectedQueue !== '' ? $expectedQueue : null],
            $routes[RecordAsyncAuditEvent::class] ?? null
        );
    }

    public function test_crud_schema_route_uses_controller_middleware_attributes(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/system/ui/crud-schema/user', 'GET'));
        $middleware = $route->gatherMiddleware();

        $this->assertContains('tenant.context', $middleware);
        $this->assertContains('api.auth', $middleware);
    }

    public function test_feature_flag_routes_use_controller_middleware_attributes(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/system/feature-flags', 'GET'));
        $middleware = $route->gatherMiddleware();

        $this->assertContains('tenant.context', $middleware);
        $this->assertContains('api.auth', $middleware);
        $this->assertContains('api.permission:system.manage', $middleware);
    }

    public function test_openapi_spec_cache_touch_extends_cached_spec_ttl(): void
    {
        $previousEnvironment = app()->environment();
        $this->app['env'] = 'production';

        config()->set('api.docs.cache_enabled', true);
        config()->set('api.docs.cache_ttl_seconds', 600);
        config()->set('api.current_version', 'v1');
        config()->set('scramble.export_path', 'api.json');

        $request = Request::create('http://localhost/docs/api.json', 'GET');
        $cacheKey = sprintf(
            'docs.openapi.spec.%s',
            sha1($request->fullUrl().'|'.(string) config('api.current_version', 'v1'))
        );

        Cache::shouldReceive('get')
            ->once()
            ->with($cacheKey)
            ->andReturn([
                'body' => '{"openapi":"3.1.0"}',
                'headers' => ['Content-Type' => 'application/json'],
            ]);

        Cache::shouldReceive('touch')
            ->once()
            ->with(
                $cacheKey,
                Mockery::on(static fn (mixed $value): bool => $value instanceof DateTimeInterface)
            )
            ->andReturnTrue();

        try {
            $response = (new CacheOpenApiSpec)->handle($request, function (): never {
                throw new RuntimeException('Cache hit should bypass downstream middleware.');
            });
        } finally {
            $this->app['env'] = $previousEnvironment;
        }

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"openapi":"3.1.0"}', $response->getContent());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }
}
