<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeDomainResourceCommandTest extends TestCase
{
    private string $scaffoldRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scaffoldRoot = base_path('build/testing/domain-resource-scaffold');
        File::deleteDirectory($this->scaffoldRoot);
        File::ensureDirectoryExists($this->scaffoldRoot.'/app/Domains/Catalog');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->scaffoldRoot);

        parent::tearDown();
    }

    public function test_it_scaffolds_a_tenant_resource_boundary_into_an_alternate_base_path(): void
    {
        $this->artisan('make:domain-resource', [
            'domain' => 'Catalog',
            'resource' => 'Product',
            '--scope' => 'tenant',
            '--base-path' => $this->scaffoldRoot,
        ])
            ->expectsOutputToContain('Created app/DTOs/Product/ListProductsInputDTO.php')
            ->expectsOutputToContain('Created app/Domains/Catalog/Http/Controllers/ProductController.php')
            ->expectsOutputToContain('Scaffolded tenant Product resource for the Catalog domain.')
            ->assertSuccessful();

        $this->assertFileExists($this->scaffoldRoot.'/app/DTOs/Product/ListProductsInputDTO.php');
        $this->assertFileExists($this->scaffoldRoot.'/app/DTOs/Product/CreateProductDTO.php');
        $this->assertFileExists($this->scaffoldRoot.'/app/Http/Requests/Api/Product/StoreProductRequest.php');
        $this->assertFileExists($this->scaffoldRoot.'/app/Domains/Catalog/Actions/ListProductsQueryAction.php');
        $this->assertFileExists($this->scaffoldRoot.'/app/Domains/Catalog/Http/Controllers/ProductController.php');
        $this->assertFileExists($this->scaffoldRoot.'/tests/Feature/ProductApiTest.php');

        $queryAction = (string) file_get_contents($this->scaffoldRoot.'/app/Domains/Catalog/Actions/ListProductsQueryAction.php');
        $service = (string) file_get_contents($this->scaffoldRoot.'/app/Domains/Catalog/Services/ProductService.php');
        $controller = (string) file_get_contents($this->scaffoldRoot.'/app/Domains/Catalog/Http/Controllers/ProductController.php');

        $this->assertStringContainsString('public function handle(int $tenantId, ListProductsInputDTO $input): Builder', $queryAction);
        $this->assertStringContainsString("->where('tenant_id', \$tenantId)", $queryAction);
        $this->assertStringContainsString('public function create(int $tenantId, CreateProductDTO $dto): Product', $service);
        $this->assertStringContainsString('replace scaffold tenant id with the resolved tenant context', $controller);
    }

    public function test_it_refuses_to_overwrite_existing_scaffold_without_force(): void
    {
        $this->artisan('make:domain-resource', [
            'domain' => 'Catalog',
            'resource' => 'Product',
            '--scope' => 'platform',
            '--base-path' => $this->scaffoldRoot,
        ])->assertSuccessful();

        $this->artisan('make:domain-resource', [
            'domain' => 'Catalog',
            'resource' => 'Product',
            '--scope' => 'platform',
            '--base-path' => $this->scaffoldRoot,
        ])
            ->expectsOutputToContain('Scaffold aborted because the following files already exist:')
            ->expectsOutputToContain('Re-run with --force to overwrite the generated scaffold.')
            ->assertExitCode(1);
    }

    public function test_it_overwrites_existing_scaffold_when_force_is_used(): void
    {
        $controllerPath = $this->scaffoldRoot.'/app/Domains/Catalog/Http/Controllers/ProductController.php';

        $this->artisan('make:domain-resource', [
            'domain' => 'Catalog',
            'resource' => 'Product',
            '--scope' => 'platform',
            '--base-path' => $this->scaffoldRoot,
        ])->assertSuccessful();

        file_put_contents($controllerPath, 'stale');

        $this->artisan('make:domain-resource', [
            'domain' => 'Catalog',
            'resource' => 'Product',
            '--scope' => 'platform',
            '--base-path' => $this->scaffoldRoot,
            '--force' => true,
        ])
            ->expectsOutputToContain('Created app/Domains/Catalog/Http/Controllers/ProductController.php')
            ->assertSuccessful();

        $controller = (string) file_get_contents($controllerPath);

        $this->assertStringContainsString('class ProductController extends ApiController', $controller);
        $this->assertStringNotContainsString('stale', $controller);
    }
}
