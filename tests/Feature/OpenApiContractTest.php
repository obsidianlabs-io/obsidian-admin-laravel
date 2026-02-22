<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_documented_operations_are_registered_for_root_and_v1_api_routes(): void
    {
        $operations = $this->parseDocumentedOperations();

        $this->assertNotEmpty($operations, 'No operations were parsed from docs/openapi.yaml');

        $routes = collect(RouteFacade::getRoutes()->getRoutes());

        $hasRoute = static function (string $uri, string $method) use ($routes): bool {
            return $routes->contains(
                static fn (Route $route): bool => $route->uri() === ltrim($uri, '/')
                    && in_array($method, $route->methods(), true)
            );
        };

        foreach ($operations as $operation) {
            $method = $operation['method'];
            $path = $operation['path'];
            $rootUri = 'api'.$path;
            $v1Uri = 'api/v1'.$path;

            $this->assertTrue(
                $hasRoute($rootUri, $method),
                "OpenAPI operation {$method} {$path} is missing route {$rootUri}"
            );
            $this->assertTrue(
                $hasRoute($v1Uri, $method),
                "OpenAPI operation {$method} {$path} is missing route {$v1Uri}"
            );
        }
    }

    public function test_public_openapi_auth_endpoints_return_standard_wrapper_shape(): void
    {
        $this->seed();

        $loginResponse = $this->postJson('/api/auth/login', [
            'userName' => 'Super',
            'password' => '123456',
        ]);
        $loginResponse->assertOk()
            ->assertJsonStructure(['code', 'msg', 'data']);

        $forgotPasswordResponse = $this->postJson('/api/auth/forgot-password', [
            'email' => 'super@obsidian.local',
        ]);
        $forgotPasswordResponse->assertOk()
            ->assertJsonStructure(['code', 'msg', 'data']);

        $resetPasswordResponse = $this->postJson('/api/auth/reset-password', [
            'email' => 'super@obsidian.local',
            'token' => 'invalid-token',
            'password' => 'Aa123456',
            'password_confirmation' => 'Aa123456',
        ]);
        $resetPasswordResponse->assertOk()
            ->assertJsonStructure(['code', 'msg', 'data']);
    }

    /**
     * @return list<array{path: string, method: string}>
     */
    private function parseDocumentedOperations(): array
    {
        $specPath = base_path('docs/openapi.yaml');
        $this->assertFileExists($specPath, 'OpenAPI spec file is missing');

        $content = File::get($specPath);
        $lines = preg_split('/\R/', $content) ?: [];

        $operations = [];
        $insidePaths = false;
        $currentPath = null;

        foreach ($lines as $line) {
            if (! $insidePaths) {
                if (preg_match('/^paths:\s*$/', $line) === 1) {
                    $insidePaths = true;
                }

                continue;
            }

            if (preg_match('/^\S/', $line) === 1) {
                break;
            }

            if (preg_match('/^\s{2}(\/[^\s:]+):\s*$/', $line, $matches) === 1) {
                $currentPath = $matches[1];

                continue;
            }

            if ($currentPath === null) {
                continue;
            }

            if (preg_match('/^\s{4}(get|post|put|patch|delete|head|options):\s*$/i', $line, $matches) === 1) {
                $operations[] = [
                    'path' => $currentPath,
                    'method' => strtoupper($matches[1]),
                ];
            }
        }

        return $operations;
    }
}
