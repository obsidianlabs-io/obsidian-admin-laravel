<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\PurgeFeatureFlagDTO;

readonly class PurgeFeatureFlagAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(PurgeFeatureFlagDTO $dto): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($dto->key)) {
            return false;
        }

        $this->featureFlagService->purgeStoredOverrides($dto->key);

        return true;
    }
}
