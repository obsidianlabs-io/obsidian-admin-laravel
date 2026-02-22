<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\ToggleFeatureFlagDTO;

readonly class ToggleFeatureFlagAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(ToggleFeatureFlagDTO $dto): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($dto->key)) {
            return false;
        }

        $this->featureFlagService->setStoredOverride(
            $dto->key,
            $this->featureFlagService->globalScopeKey(),
            $dto->enabled
        );

        return true;
    }
}
