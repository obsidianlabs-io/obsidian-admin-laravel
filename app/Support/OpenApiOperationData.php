<?php

declare(strict_types=1);

namespace App\Support;

final readonly class OpenApiOperationData
{
    public function __construct(
        public string $path,
        public string $method,
        public string $summary,
        public bool $has2xxResponse,
    ) {}
}
