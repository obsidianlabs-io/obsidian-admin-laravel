<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\SetFeatureFlagOverrideDTO;

readonly class SetFeatureFlagOverrideAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(SetFeatureFlagOverrideDTO $dto): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($dto->key)) {
            return false;
        }

        $this->featureFlagService->setStoredOverride($dto->key, $dto->scope, $dto->enabled);

        return true;
    }
}
