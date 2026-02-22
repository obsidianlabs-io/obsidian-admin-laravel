<?php

declare(strict_types=1);

namespace App\DTOs\FeatureFlag;

readonly class ListFeatureFlagsDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public string $keyword,
    ) {}
}
