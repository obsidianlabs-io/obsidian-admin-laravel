<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\System\Models\ThemeProfile;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ThemeProfileSeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['theme_profiles']);
    }

    protected function module(): string
    {
        return 'theme.profiles';
    }

    /**
     * @return array<int, array{
     *   scope_type: string,
     *   scope_id: int|null,
     *   name: string,
     *   status: string,
     *   config: array<string, mixed>,
     *   version: int
     * }>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::projectThemeProfile(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var array{
         *   scope_type: string,
         *   scope_id: int|null,
         *   name: string,
         *   status: string,
         *   config: array<string, mixed>,
         *   version: int
         * } $profile
         */
        $profile = $payload;

        ThemeProfile::query()->updateOrCreate(
            ['scope_key' => ThemeProfile::scopeKey(ThemeProfile::SCOPE_PLATFORM, null)],
            [
                'scope_type' => $profile['scope_type'],
                'scope_id' => $profile['scope_id'],
                'name' => $profile['name'],
                'status' => $profile['status'],
                'config' => $profile['config'],
                'version' => $profile['version'],
            ]
        );

        // Project-level base config applies to all tenants.
        $tenantScopeIds = ThemeProfile::query()
            ->where('scope_type', ThemeProfile::SCOPE_TENANT)
            ->pluck('scope_id')
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        ThemeProfile::query()
            ->where('scope_type', ThemeProfile::SCOPE_TENANT)
            ->delete();

        $this->invalidateThemeCache($tenantScopeIds);
    }

    /**
     * @param  list<int>  $tenantScopeIds
     */
    private function invalidateThemeCache(array $tenantScopeIds): void
    {
        // Cache invalidation is best-effort during seeding. CI/local bootstrap
        // may intentionally run without Redis even when .env.example defaults to it.
        try {
            Cache::forget('theme.profile.platform.0');

            foreach ($tenantScopeIds as $scopeId) {
                Cache::forget(sprintf('theme.profile.tenant.%d', $scopeId));
            }
        } catch (Throwable) {
            // Ignore cache backend connectivity issues during seeding.
        }
    }
}
