<?php

declare(strict_types=1);

namespace App\DTOs\FeatureFlag;

readonly class SetFeatureFlagOverrideDTO
{
    public function __construct(
        public string $key,
        public string $scope,
        public bool $enabled,
    ) {}
}
