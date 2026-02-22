<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\ForgetFeatureFlagOverrideDTO;

readonly class ForgetFeatureFlagOverrideAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(ForgetFeatureFlagOverrideDTO $dto): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($dto->key)) {
            return false;
        }

        $this->featureFlagService->forgetStoredOverride($dto->key, $dto->scope);

        return true;
    }
}
