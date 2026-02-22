<?php

declare(strict_types=1);

namespace App\Domains\System\Actions\FeatureFlag;

use App\Domains\System\Services\FeatureFlagService;
use App\DTOs\FeatureFlag\ListFeatureFlagsDTO;

readonly class ListFeatureFlagsAction
{
    public function __construct(
        private FeatureFlagService $featureFlagService,
    ) {}

    /**
     * @return array{
     *   current:int,
     *   size:int,
     *   total:int,
     *   records:list<array{
     *     key:string,
     *     enabled:bool,
     *     percentage:int,
     *     platform_only:bool,
     *     tenant_only:bool,
     *     role_codes:list<string>,
     *     global_override:?bool
     *   }>
     * }
     */
    public function __invoke(ListFeatureFlagsDTO $dto): array
    {
        $definitions = config('features.definitions', []);
        $flags = [];

        if (! is_array($definitions)) {
            return [
                'current' => $dto->current,
                'size' => $dto->size,
                'total' => 0,
                'records' => [],
            ];
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

            $flags[] = [
                'key' => $featureKey,
                'enabled' => (bool) ($definition['enabled'] ?? true),
                'percentage' => (int) ($definition['percentage'] ?? 100),
                'platform_only' => (bool) ($definition['platform_only'] ?? false),
                'tenant_only' => (bool) ($definition['tenant_only'] ?? false),
                'role_codes' => $roleCodes,
                'global_override' => $globalOverride,
            ];
        }

        $total = count($flags);
        $offset = ($dto->current - 1) * $dto->size;
        $records = array_values(array_slice($flags, $offset, $dto->size));

        return [
            'current' => $dto->current,
            'size' => $dto->size,
            'total' => $total,
            'records' => $records,
        ];
    }
}
