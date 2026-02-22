<?php

declare(strict_types=1);

namespace App\DTOs\FeatureFlag;

readonly class ForgetFeatureFlagOverrideDTO
{
    public function __construct(
        public string $key,
        public string $scope,
    ) {}
}
