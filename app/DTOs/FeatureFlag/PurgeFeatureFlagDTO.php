<?php

declare(strict_types=1);

namespace App\DTOs\FeatureFlag;

readonly class PurgeFeatureFlagDTO
{
    public function __construct(
        public string $key,
    ) {}
}
