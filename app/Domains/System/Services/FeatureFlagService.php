<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Shared\Services\ApiCacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

class FeatureFlagService
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    private const GLOBAL_SCOPE_KEY = '__global__';

    private bool $definitionsRegistered = false;

    public function registerDefinitions(): void
    {
        if ($this->definitionsRegistered) {
            return;
        }

        foreach ($this->featureDefinitions() as $featureKey => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            Feature::define($featureKey, function ($scope = null) use ($featureKey): bool {
                return $this->evaluateDefinition(
                    $featureKey,
                    is_scalar($scope) ? (string) $scope : ''
                );
            });
        }

        $this->definitionsRegistered = true;
    }

    /**
     * @param  list<string>  $roleCodes
     */
    public function isMenuFeatureEnabled(string $menuFeatureFlag, ?int $tenantId, array $roleCodes): bool
    {
        $featureKey = sprintf('menu.%s', trim($menuFeatureFlag));

        if (! $this->hasFeatureDefinition($featureKey)) {
            return (bool) config(sprintf('menu.features.%s', $menuFeatureFlag), true);
        }

        return $this->isFeatureEnabled($featureKey, $tenantId, $roleCodes);
    }

    public function hasFeatureDefinition(string $featureKey): bool
    {
        return $this->definition($featureKey) !== [];
    }

    /**
     * @param  list<string>  $roleCodes
     */
    public function isFeatureEnabled(string $featureKey, ?int $tenantId, array $roleCodes): bool
    {
        $this->registerDefinitions();

        $scope = $this->scopeKey($tenantId, $roleCodes);

        $globalOverride = $this->readStoredOverride($featureKey, self::GLOBAL_SCOPE_KEY);
        if (is_bool($globalOverride)) {
            return $globalOverride;
        }

        $scopeOverride = $this->readStoredOverride($featureKey, $scope);
        if (is_bool($scopeOverride)) {
            return $scopeOverride;
        }

        return $this->evaluateDefinition($featureKey, $scope);
    }

    public function globalScopeKey(): string
    {
        return self::GLOBAL_SCOPE_KEY;
    }

    public function setStoredOverride(string $featureKey, string $scope, bool $enabled): void
    {
        $table = $this->featureTable();
        if ($table === null) {
            return;
        }

        $now = now();
        DB::table($table)->upsert([
            [
                'name' => $featureKey,
                'scope' => $scope,
                'value' => json_encode($enabled, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['name', 'scope'], ['value', 'updated_at']);
        $this->apiCacheService->bump('features');
    }

    public function forgetStoredOverride(string $featureKey, string $scope): void
    {
        $table = $this->featureTable();
        if ($table === null) {
            return;
        }

        DB::table($table)
            ->where('name', $featureKey)
            ->where('scope', $scope)
            ->delete();
        $this->apiCacheService->bump('features');
    }

    public function purgeStoredOverrides(string $featureKey): void
    {
        $table = $this->featureTable();
        if ($table === null) {
            return;
        }

        DB::table($table)->where('name', $featureKey)->delete();
        $this->apiCacheService->bump('features');
    }

    public function getStoredOverride(string $featureKey, string $scope): ?bool
    {
        return $this->readStoredOverride($featureKey, $scope);
    }

    /**
     * @param  list<string>  $roleCodes
     */
    public function scopeKey(?int $tenantId, array $roleCodes): string
    {
        $tenantScopeId = $tenantId ?? 0;
        $normalizedRoles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => trim((string) $role),
            $roleCodes
        ), static fn (string $role): bool => $role !== '')));
        sort($normalizedRoles);

        return sprintf(
            'tenant:%d|roles:%s',
            $tenantScopeId,
            $normalizedRoles === [] ? '-' : implode(',', $normalizedRoles)
        );
    }

    /**
     * @return array{
     *   tenantId: int,
     *   roleCodes: list<string>
     * }
     */
    public function parseScopeKey(string $scopeKey): array
    {
        $tenantId = 0;
        $roleCodes = [];

        if (preg_match('/tenant:(\d+)\|roles:(.*)$/', $scopeKey, $matches) === 1) {
            $tenantId = max(0, (int) $matches[1]);
            $rolesRaw = trim((string) $matches[2]);
            if ($rolesRaw !== '' && $rolesRaw !== '-') {
                $roleCodes = array_values(array_filter(array_map(
                    static fn (string $role): string => trim($role),
                    explode(',', $rolesRaw)
                ), static fn (string $role): bool => $role !== ''));
            }
        }

        return [
            'tenantId' => $tenantId,
            'roleCodes' => $roleCodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $featureKey): array
    {
        $definitions = $this->featureDefinitions();
        $definition = $definitions[$featureKey] ?? [];

        return is_array($definition) ? $definition : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function featureDefinitions(): array
    {
        $definitions = config('features.definitions', []);

        return is_array($definitions) ? $definitions : [];
    }

    private function evaluateDefinition(string $featureKey, string $scopeKey): bool
    {
        $definition = $this->definition($featureKey);
        if ($definition === []) {
            return false;
        }

        $enabled = filter_var($definition['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            return false;
        }

        $scope = $this->parseScopeKey($scopeKey);
        $tenantId = (int) $scope['tenantId'];
        $roleCodes = $scope['roleCodes'];

        if ((bool) ($definition['platform_only'] ?? false) && $tenantId > 0) {
            return false;
        }

        if ((bool) ($definition['tenant_only'] ?? false) && $tenantId === 0) {
            return false;
        }

        $allowedTenantIds = $this->normalizeIntList($definition['tenant_ids'] ?? []);
        if ($allowedTenantIds !== [] && ! in_array($tenantId, $allowedTenantIds, true)) {
            return false;
        }

        $allowedRoleCodes = $this->normalizeStringList($definition['role_codes'] ?? []);
        if ($allowedRoleCodes !== [] && array_intersect($allowedRoleCodes, $roleCodes) === []) {
            return false;
        }

        $percentage = max(0, min(100, (int) ($definition['percentage'] ?? 100)));
        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        return (abs(crc32(sprintf('%s|%s', $featureKey, $scopeKey))) % 100) < $percentage;
    }

    private function readStoredOverride(string $featureKey, string $scope): ?bool
    {
        $table = $this->featureTable();
        if ($table === null) {
            return null;
        }

        $value = DB::table($table)
            ->where('name', $featureKey)
            ->where('scope', $scope)
            ->value('value');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_bool($decoded) ? $decoded : null;
    }

    private function featureTable(): ?string
    {
        $table = trim((string) config('pennant.stores.database.table', 'features'));

        return $table !== '' ? $table : null;
    }

    /**
     * @return list<int>
     */
    private function normalizeIntList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return Collection::make($values)
            ->map(static fn (mixed $value): int => max(0, (int) $value))
            ->filter(static fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return Collection::make($values)
            ->map(static fn (mixed $value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
