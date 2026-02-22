<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OpenApiLintCommandTest extends TestCase
{
    public function test_openapi_lint_passes_for_repository_spec(): void
    {
        $this->artisan('openapi:lint')
            ->expectsOutputToContain('OpenAPI lint passed')
            ->assertExitCode(0);
    }

    public function test_openapi_lint_fails_for_invalid_document(): void
    {
        $invalidSpecPath = base_path('storage/framework/testing/openapi-invalid.yaml');
        File::ensureDirectoryExists(dirname($invalidSpecPath));

        File::put($invalidSpecPath, <<<'YAML'
openapi: 3.0.3
info:
  title: Invalid API
  version: 1.0.0
servers:
  - url: http://localhost
paths:
  /auth/login:
    post:
      responses:
        '400':
          description: Bad request
YAML
        );

        try {
            $this->artisan(sprintf('openapi:lint --file=%s', $invalidSpecPath))
                ->expectsOutputToContain('OpenAPI lint failed')
                ->expectsOutputToContain('must define summary')
                ->expectsOutputToContain('must define at least one 2xx response')
                ->assertExitCode(1);
        } finally {
            File::delete($invalidSpecPath);
        }
    }
}
