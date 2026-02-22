<?php

declare(strict_types=1);

namespace App\DTOs\FeatureFlag;

readonly class ToggleFeatureFlagDTO
{
    public function __construct(
        public string $key,
        public bool $enabled,
    ) {}
}
