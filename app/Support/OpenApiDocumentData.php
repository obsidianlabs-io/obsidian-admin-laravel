<?php

declare(strict_types=1);

namespace App\Support;

final readonly class OpenApiDocumentData
{
    /**
     * @param  list<string>  $serverUrls
     * @param  list<OpenApiOperationData>  $operations
     */
    public function __construct(
        public string $openapiVersion,
        public string $infoTitle,
        public string $infoVersion,
        public array $serverUrls,
        public array $operations,
    ) {}
}
