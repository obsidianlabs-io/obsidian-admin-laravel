<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;

class MakeDomainResourceCommand extends Command
{
    protected $signature = 'make:domain-resource
        {domain : Existing domain name under app/Domains}
        {resource : Singular resource name, preferably StudlyCase}
        {--scope=tenant : Scaffold scope semantics (tenant|platform)}
        {--base-path= : Alternate target root used for testing or external scaffolding}
        {--force : Overwrite existing scaffold files}';

    protected $description = 'Scaffold a domain resource boundary (DTOs, requests, action, service, controller, resource, and test)';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $domain = Str::studly(trim((string) $this->argument('domain')));
        $resource = Str::studly(trim((string) $this->argument('resource')));
        $scope = strtolower(trim((string) $this->option('scope')));

        if ($domain === '' || $resource === '') {
            $this->error('Domain and resource names must be non-empty StudlyCase values.');

            return self::FAILURE;
        }

        if (! in_array($scope, ['tenant', 'platform'], true)) {
            $this->error('Invalid --scope value. Use tenant or platform.');

            return self::FAILURE;
        }

        $targetBasePath = $this->resolveTargetBasePath();
        $domainRoot = $targetBasePath.DIRECTORY_SEPARATOR.'app/Domains/'.$domain;
        if (! $this->files->isDirectory($domainRoot)) {
            $this->error(sprintf(
                'Domain [%s] does not exist under %s. Create the domain directory first.',
                $domain,
                $domainRoot
            ));

            return self::FAILURE;
        }

        $context = $this->buildContext($domain, $resource, $scope);
        $files = $this->scaffoldFiles($targetBasePath, $context);
        $existing = array_values(array_filter($files, fn (array $file): bool => $this->files->exists($file['target'])));

        if ($existing !== [] && ! (bool) $this->option('force')) {
            $this->error('Scaffold aborted because the following files already exist:');
            foreach ($existing as $file) {
                $this->line(' - '.$this->relativePath($targetBasePath, $file['target']));
            }
            $this->line('Re-run with --force to overwrite the generated scaffold.');

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->files->ensureDirectoryExists(dirname($file['target']));
            $this->files->put($file['target'], $this->renderStub($file['stub'], $context));
            $this->line('Created '.$this->relativePath($targetBasePath, $file['target']));
        }

        $this->newLine();
        $this->info(sprintf(
            'Scaffolded %s %s resource for the %s domain.',
            $scope,
            $resource,
            $domain
        ));
        $this->line('Next steps:');
        $this->line(sprintf('  1. Create the %s table/migration and wire model relationships.', $context['{{TABLE_NAME}}']));
        $this->line(sprintf('  2. Register routes for %sController in the appropriate routes/api module.', $context['{{RESOURCE_STUDLY}}']));
        $this->line('  3. Replace scaffold TODO comments with real permission, tenant, and optimistic-lock rules.');
        $this->line('  4. Extend the generated feature test and add OpenAPI paths/examples.');

        return self::SUCCESS;
    }

    private function resolveTargetBasePath(): string
    {
        $basePathOption = trim((string) $this->option('base-path'));
        if ($basePathOption === '') {
            return base_path();
        }

        $resolved = str_starts_with($basePathOption, DIRECTORY_SEPARATOR)
            ? $basePathOption
            : base_path($basePathOption);

        $this->files->ensureDirectoryExists($resolved);

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, string>
     */
    private function buildContext(string $domain, string $resource, string $scope): array
    {
        $pluralStudly = Str::pluralStudly($resource);
        $resourceCamel = Str::camel($resource);
        $resourceKebab = Str::kebab($resource);
        $tableName = Str::snake($pluralStudly);
        $codeField = $resourceCamel.'Code';
        $nameField = $resourceCamel.'Name';
        $listHandleSignature = $scope === 'tenant'
            ? sprintf('public function handle(int $tenantId, List%sInputDTO $input): Builder', $pluralStudly)
            : sprintf('public function handle(List%sInputDTO $input): Builder', $pluralStudly);
        $queryScopeLine = $scope === 'tenant'
            ? "            ->where('tenant_id', \$tenantId)"
            : '            // TODO: add platform visibility constraints when this resource is not globally readable';
        $tenantSelectField = $scope === 'tenant' ? "'tenant_id',\n                " : '';
        $tenantFillableField = $scope === 'tenant' ? "        'tenant_id',\n" : '';
        $resourceTenantField = $scope === 'tenant'
            ? "            'tenantId' => (string) (\$this->tenant_id ?? ''),\n"
            : '';
        $responseTenantField = $scope === 'tenant'
            ? "            'tenantId' => (string) (\${$resourceCamel}->tenant_id ?? ''),\n"
            : '';
        $controllerListSetup = $scope === 'tenant'
            ? "        \$tenantId = 0;\n        // TODO: replace scaffold tenant id with the resolved tenant context and permission checks.\n\n        \$query = \$list{$pluralStudly}Query->handle(\$tenantId, \$input);"
            : "        // TODO: resolve platform-scoped permission checks before using the query action.\n        \$query = \$list{$pluralStudly}Query->handle(\$input);";
        $controllerCreateSetup = $scope === 'tenant'
            ? "        \$tenantId = 0;\n        // TODO: replace scaffold tenant id with the resolved tenant context and permission checks.\n\n        \${$resourceCamel} = \$this->{$resourceCamel}Service->create(\$tenantId, \$dto);"
            : "        // TODO: resolve platform-scoped permission checks before persisting.\n        \${$resourceCamel} = \$this->{$resourceCamel}Service->create(\$dto);";
        $controllerFindModel = $scope === 'tenant'
            ? sprintf("        \$tenantId = 0;\n        // TODO: replace scaffold tenant id with the resolved tenant context and permission checks.\n        \$%s = %s::query()->where('tenant_id', \$tenantId)->findOrFail(\$id);", $resourceCamel, $resource)
            : sprintf("        // TODO: resolve platform-scoped permission checks before loading the model.\n        \$%s = %s::query()->findOrFail(\$id);", $resourceCamel, $resource);
        $serviceCreateSignature = $scope === 'tenant'
            ? sprintf('public function create(int $tenantId, Create%sDTO $dto): %s', $resource, $resource)
            : sprintf('public function create(Create%sDTO $dto): %s', $resource, $resource);
        $serviceCreateTenantLine = $scope === 'tenant' ? "            'tenant_id' => \$tenantId,\n" : '';
        $scopeComment = $scope === 'tenant'
            ? 'Resolve tenant-scoped auth context, tenant ownership, and permission checks before wiring routes.'
            : 'Resolve platform-scoped permission checks before wiring routes.';
        $testScopeNotes = $scope === 'tenant'
            ? 'tenant context, permission middleware, and scope assertions'
            : 'platform permission middleware and scope assertions';

        return [
            '{{DOMAIN_STUDLY}}' => $domain,
            '{{DOMAIN_NAMESPACE}}' => 'App\\Domains\\'.$domain,
            '{{RESOURCE_STUDLY}}' => $resource,
            '{{RESOURCE_CAMEL}}' => $resourceCamel,
            '{{RESOURCE_KEBAB}}' => $resourceKebab,
            '{{RESOURCE_SNAKE}}' => Str::snake($resource),
            '{{PLURAL_STUDLY}}' => $pluralStudly,
            '{{DTO_NAMESPACE}}' => 'App\\DTOs\\'.$resource,
            '{{REQUEST_NAMESPACE}}' => 'App\\Http\\Requests\\Api\\'.$resource,
            '{{CODE_FIELD}}' => $codeField,
            '{{NAME_FIELD}}' => $nameField,
            '{{TABLE_NAME}}' => $tableName,
            '{{SCOPE}}' => $scope,
            '{{SCOPE_COMMENT}}' => $scopeComment,
            '{{LIST_HANDLE_SIGNATURE}}' => $listHandleSignature,
            '{{QUERY_SCOPE_LINE}}' => $queryScopeLine,
            '{{TENANT_SELECT_FIELD}}' => $tenantSelectField,
            '{{TENANT_FILLABLE_FIELD}}' => $tenantFillableField,
            '{{RESOURCE_TENANT_FIELD}}' => $resourceTenantField,
            '{{RESPONSE_TENANT_FIELD}}' => $responseTenantField,
            '{{CONTROLLER_LIST_SETUP}}' => $controllerListSetup,
            '{{CONTROLLER_CREATE_SETUP}}' => $controllerCreateSetup,
            '{{CONTROLLER_FIND_MODEL}}' => $controllerFindModel,
            '{{SERVICE_CREATE_SIGNATURE}}' => $serviceCreateSignature,
            '{{SERVICE_CREATE_TENANT_LINE}}' => $serviceCreateTenantLine,
            '{{TEST_SCOPE_NOTES}}' => $testScopeNotes,
        ];
    }

    /**
     * @param  array<string, string>  $context
     * @return list<array{stub:string,target:string}>
     */
    private function scaffoldFiles(string $targetBasePath, array $context): array
    {
        $resource = $context['{{RESOURCE_STUDLY}}'];
        $domain = $context['{{DOMAIN_STUDLY}}'];
        $plural = $context['{{PLURAL_STUDLY}}'];

        return [
            [
                'stub' => 'dto-list.stub',
                'target' => $targetBasePath.'/app/DTOs/'.$resource.'/List'.$plural.'InputDTO.php',
            ],
            [
                'stub' => 'dto-create.stub',
                'target' => $targetBasePath.'/app/DTOs/'.$resource.'/Create'.$resource.'DTO.php',
            ],
            [
                'stub' => 'dto-update.stub',
                'target' => $targetBasePath.'/app/DTOs/'.$resource.'/Update'.$resource.'DTO.php',
            ],
            [
                'stub' => 'request-list.stub',
                'target' => $targetBasePath.'/app/Http/Requests/Api/'.$resource.'/List'.$plural.'Request.php',
            ],
            [
                'stub' => 'request-store.stub',
                'target' => $targetBasePath.'/app/Http/Requests/Api/'.$resource.'/Store'.$resource.'Request.php',
            ],
            [
                'stub' => 'request-update.stub',
                'target' => $targetBasePath.'/app/Http/Requests/Api/'.$resource.'/Update'.$resource.'Request.php',
            ],
            [
                'stub' => 'model.stub',
                'target' => $targetBasePath.'/app/Domains/'.$domain.'/Models/'.$resource.'.php',
            ],
            [
                'stub' => 'query-action.stub',
                'target' => $targetBasePath.'/app/Domains/'.$domain.'/Actions/List'.$plural.'QueryAction.php',
            ],
            [
                'stub' => 'service.stub',
                'target' => $targetBasePath.'/app/Domains/'.$domain.'/Services/'.$resource.'Service.php',
            ],
            [
                'stub' => 'list-resource.stub',
                'target' => $targetBasePath.'/app/Domains/'.$domain.'/Http/Resources/'.$resource.'ListResource.php',
            ],
            [
                'stub' => 'controller.stub',
                'target' => $targetBasePath.'/app/Domains/'.$domain.'/Http/Controllers/'.$resource.'Controller.php',
            ],
            [
                'stub' => 'feature-test.stub',
                'target' => $targetBasePath.'/tests/Feature/'.$resource.'ApiTest.php',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $context
     */
    private function renderStub(string $stubName, array $context): string
    {
        $stubPath = base_path('stubs/domain-resource/'.$stubName);
        if (! $this->files->exists($stubPath)) {
            throw new RuntimeException('Missing scaffold stub: '.$stubPath);
        }

        return strtr((string) $this->files->get($stubPath), $context);
    }

    private function relativePath(string $targetBasePath, string $path): string
    {
        return ltrim(Str::after($path, rtrim($targetBasePath, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR);
    }
}
