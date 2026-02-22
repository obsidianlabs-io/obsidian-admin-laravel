<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\OpenApiSpecInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OpenApiLintCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'openapi:lint {--file=docs/openapi.yaml : OpenAPI document path}';

    /**
     * @var string
     */
    protected $description = 'Lint OpenAPI document quality and fail on contract hygiene issues';

    public function __construct(private readonly OpenApiSpecInspector $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $specPath = trim((string) $this->option('file'));
        if ($specPath === '') {
            $specPath = 'docs/openapi.yaml';
        }

        $resolvedSpecPath = str_starts_with($specPath, '/')
            ? $specPath
            : base_path($specPath);

        if (! File::exists($resolvedSpecPath)) {
            $this->error(sprintf('OpenAPI file not found: %s', $resolvedSpecPath));

            return self::FAILURE;
        }

        $document = $this->inspector->inspect($resolvedSpecPath);
        $errors = $this->collectErrors($document);

        if ($errors !== []) {
            $this->line('OpenAPI lint failed:');
            foreach ($errors as $error) {
                $this->line(sprintf('  - %s', $error));
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OpenAPI lint passed (%d operations).',
            count($document['operations'])
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *   openapiVersion: string,
     *   infoTitle: string,
     *   infoVersion: string,
     *   serverUrls: list<string>,
     *   operations: list<array{
     *     path: string,
     *     method: string,
     *     summary: string,
     *     has2xxResponse: bool
     *   }>
     * }  $document
     * @return list<string>
     */
    private function collectErrors(array $document): array
    {
        $errors = [];

        if (
            $document['openapiVersion'] === ''
            || preg_match('/^3\.\d+(\.\d+)?$/', $document['openapiVersion']) !== 1
        ) {
            $errors[] = sprintf(
                'openapi version must be 3.x (found: %s)',
                $document['openapiVersion'] === '' ? 'empty' : $document['openapiVersion']
            );
        }

        if ($document['infoTitle'] === '') {
            $errors[] = 'info.title is required';
        }

        if ($document['infoVersion'] === '') {
            $errors[] = 'info.version is required';
        }

        if ($document['serverUrls'] === []) {
            $errors[] = 'at least one server url is required under servers';
        }

        if ($document['operations'] === []) {
            $errors[] = 'no operations found under paths';

            return $errors;
        }

        foreach ($document['operations'] as $operation) {
            $operationLabel = sprintf('%s %s', $operation['method'], $operation['path']);

            if (
                $operation['path'] !== '/'
                && str_ends_with($operation['path'], '/')
            ) {
                $errors[] = sprintf('%s path should not end with "/"', $operationLabel);
            }

            if ($operation['summary'] === '') {
                $errors[] = sprintf('%s must define summary', $operationLabel);
            }

            if (! $operation['has2xxResponse']) {
                $errors[] = sprintf('%s must define at least one 2xx response', $operationLabel);
            }
        }

        return $errors;
    }
}
