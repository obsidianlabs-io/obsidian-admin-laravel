<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Data\FeatureFlagListPageData;
use App\Domains\System\Data\FeatureFlagRecordData;
use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\ListFeatureFlagsDTO;

readonly class ListFeatureFlagsAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function __invoke(ListFeatureFlagsDTO $dto): FeatureFlagListPageData
    {
        $definitions = config('features.definitions', []);
        $flags = [];

        if (! is_array($definitions)) {
            return new FeatureFlagListPageData(
                current: $dto->current,
                size: $dto->size,
                total: 0,
                records: [],
            );
        }

        foreach ($definitions as $key => $definition) {
            $featureKey = trim((string) $key);
            if ($featureKey === '') {
                continue;
            }

            if ($dto->keyword !== '' && stripos($featureKey, $dto->keyword) === false) {
                continue;
            }

            if (! is_array($definition)) {
                $definition = [];
            }

            $globalOverride = $this->featureFlagService->getStoredOverride(
                $featureKey,
                $this->featureFlagService->globalScopeKey()
            );

            $roleCodes = array_values(array_filter(array_map(
                static fn (mixed $code): string => trim((string) $code),
                is_array($definition['role_codes'] ?? null) ? $definition['role_codes'] : []
            ), static fn (string $code): bool => $code !== ''));

            $flags[] = new FeatureFlagRecordData(
                key: $featureKey,
                enabled: (bool) ($definition['enabled'] ?? true),
                percentage: (int) ($definition['percentage'] ?? 100),
                platformOnly: (bool) ($definition['platform_only'] ?? false),
                tenantOnly: (bool) ($definition['tenant_only'] ?? false),
                roleCodes: $roleCodes,
                globalOverride: $globalOverride,
            );
        }

        $total = count($flags);
        $offset = ($dto->current - 1) * $dto->size;
        $records = array_slice($flags, $offset, $dto->size);

        return new FeatureFlagListPageData(
            current: $dto->current,
            size: $dto->size,
            total: $total,
            records: $records,
        );
    }
}
