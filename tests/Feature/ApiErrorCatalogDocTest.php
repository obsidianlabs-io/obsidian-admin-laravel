<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiErrorCatalogDocTest extends TestCase
{
    public function test_api_error_catalog_document_exists_and_contains_core_codes(): void
    {
        $path = base_path('docs/api-error-catalog.md');

        $this->assertFileExists($path, 'API error catalog document is missing');

        $content = File::get($path);

        $requiredCodes = [
            '0000',
            '1001',
            '1002',
            '1003',
            '1009',
            '4040',
            '4050',
            '4290',
            '5000',
            '8888',
            '9999',
        ];

        foreach ($requiredCodes as $code) {
            $this->assertStringContainsString($code, $content, sprintf('Missing error code %s in catalog doc', $code));
        }
    }
}
