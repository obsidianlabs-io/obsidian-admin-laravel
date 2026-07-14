<?php

declare(strict_types=1);

namespace App\Domains\System\Actions;

use App\Domains\System\Data\FeatureFlagListPageData;
use App\Domains\System\Data\FeatureFlagRecordData;
use App\Domains\System\Services\FeatureFlagService;

readonly class FeatureFlagAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    public function list(int $current, int $size, string $keyword): FeatureFlagListPageData
    {
        $definitions = config('features.definitions', []);
        $flags = [];

        if (! is_array($definitions)) {
            return new FeatureFlagListPageData(
                current: $current,
                size: $size,
                total: 0,
                records: [],
            );
        }

        foreach ($definitions as $key => $definition) {
            $featureKey = trim((string) $key);
            if ($featureKey === '') {
                continue;
            }

            if ($keyword !== '' && stripos($featureKey, $keyword) === false) {
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
        $offset = ($current - 1) * $size;
        $records = array_slice($flags, $offset, $size);

        return new FeatureFlagListPageData(
            current: $current,
            size: $size,
            total: $total,
            records: $records,
        );
    }

    public function toggle(string $key, bool $enabled): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($key)) {
            return false;
        }

        $this->featureFlagService->setStoredOverride(
            $key,
            $this->featureFlagService->globalScopeKey(),
            $enabled
        );

        return true;
    }

    public function purge(string $key): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($key)) {
            return false;
        }

        $this->featureFlagService->purgeStoredOverrides($key);

        return true;
    }

    public function setOverride(string $key, string $scope, bool $enabled): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($key)) {
            return false;
        }

        $this->featureFlagService->setStoredOverride($key, $scope, $enabled);

        return true;
    }

    public function forgetOverride(string $key, string $scope): bool
    {
        if (! $this->featureFlagService->hasFeatureDefinition($key)) {
            return false;
        }

        $this->featureFlagService->forgetStoredOverride($key, $scope);

        return true;
    }
}
