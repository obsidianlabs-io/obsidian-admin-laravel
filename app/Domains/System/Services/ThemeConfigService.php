<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Access\Models\User;
use App\Domains\System\Models\ThemeProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ThemeConfigService
{
    /**
     * @return array{scopeType: 'platform', scopeId: null, scopeName: string}
     */
    public function resolveActorScope(User $user, ?int $tenantId): array
    {
        // Project-level shared theme config for all scopes.
        unset($user, $tenantId);

        return [
            'scopeType' => ThemeProfile::SCOPE_PLATFORM,
            'scopeId' => null,
            'scopeName' => 'Project Default',
        ];
    }

    /**
     * @return array{
     *   config: array{
     *     themeScheme: 'light'|'dark'|'auto',
     *     themeColor: string,
     *     themeRadius: int,
     *     headerHeight: int,
     *     siderWidth: int,
     *     layoutMode: string,
     *     darkSider: bool,
     *     tabVisible: bool,
     *     breadcrumbVisible: bool,
     *     multilingualVisible: bool,
     *     globalSearchVisible: bool,
     *     themeConfigVisible: bool
     *   },
     *   profileVersion: int
     * }
     */
    public function resolveEffectiveConfig(?int $tenantId, ?string $userThemeSchema = null): array
    {
        $defaults = $this->defaultConfig();
        $platformScope = $this->scopePayload(ThemeProfile::SCOPE_PLATFORM, null);
        unset($tenantId);

        $merged = array_replace($defaults, $platformScope['config']);
        $sanitized = $this->sanitizeConfig($merged);

        $themeSchema = trim((string) ($userThemeSchema ?? ''));
        if (in_array($themeSchema, $this->allowedSchemes(), true)) {
            $sanitized['themeScheme'] = $themeSchema;
        }

        return [
            'config' => $sanitized,
            'profileVersion' => (int) $platformScope['version'],
        ];
    }

    /**
     * @return array{
     *   scopeType: 'platform'|'tenant',
     *   scopeId: int|null,
     *   scopeName: string,
     *   config: array{
     *     themeScheme: 'light'|'dark'|'auto',
     *     themeColor: string,
     *     themeRadius: int,
     *     headerHeight: int,
     *     siderWidth: int,
     *     layoutMode: string,
     *     darkSider: bool,
     *     tabVisible: bool,
     *     breadcrumbVisible: bool,
     *     multilingualVisible: bool,
     *     globalSearchVisible: bool,
     *     themeConfigVisible: bool
     *   },
     *   version: int
     * }
     */
    public function describeScopeConfig(string $scopeType, ?int $scopeId, string $scopeName): array
    {
        $payload = $this->scopePayload($scopeType, $scopeId);

        return [
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'scopeName' => $scopeName,
            'config' => $this->sanitizeConfig($payload['config']),
            'version' => (int) $payload['version'],
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array{
     *   scopeType: 'platform'|'tenant',
     *   scopeId: int|null,
     *   scopeName: string,
     *   config: array{
     *     themeScheme: 'light'|'dark'|'auto',
     *     themeColor: string,
     *     themeRadius: int,
     *     headerHeight: int,
     *     siderWidth: int,
     *     layoutMode: string,
     *     darkSider: bool,
     *     tabVisible: bool,
     *     breadcrumbVisible: bool,
     *     multilingualVisible: bool,
     *     globalSearchVisible: bool,
     *     themeConfigVisible: bool
     *   },
     *   version: int
     * }
     */
    public function updateScopeConfig(
        string $scopeType,
        ?int $scopeId,
        string $scopeName,
        array $changes,
        int $actorUserId
    ): array {
        $scopeKey = ThemeProfile::scopeKey($scopeType, $scopeId);
        $normalizedChanges = $this->extractEditableConfig($changes);

        $profile = DB::transaction(function () use ($scopeType, $scopeId, $scopeKey, $normalizedChanges, $actorUserId, $scopeName): ThemeProfile {
            $existing = ThemeProfile::query()
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            $currentConfig = is_array($existing?->config) ? $existing->config : [];
            $nextConfig = $this->sanitizeConfig(array_replace($currentConfig, $normalizedChanges));
            $storedConfig = $this->diffFromDefault($nextConfig);

            if (! $existing) {
                return ThemeProfile::query()->create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'scope_key' => $scopeKey,
                    'name' => $scopeName,
                    'status' => '1',
                    'config' => $storedConfig,
                    'version' => 1,
                    'updated_by' => $actorUserId,
                ]);
            }

            if (
                $this->sanitizeConfig($currentConfig) === $nextConfig
                && (string) ($existing->name ?? '') === $scopeName
            ) {
                return $existing;
            }

            $existing->fill([
                'name' => $scopeName,
                'status' => '1',
                'config' => $storedConfig,
                'version' => ((int) $existing->version) + 1,
                'updated_by' => $actorUserId,
            ]);
            $existing->save();

            return $existing;
        });

        $this->forgetScopeCache($scopeType, $scopeId);

        return [
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'scopeName' => $scopeName,
            'config' => $this->sanitizeConfig(is_array($profile->config) ? $profile->config : []),
            'version' => (int) $profile->version,
        ];
    }

    /**
     * @return array{
     *   scopeType: 'platform'|'tenant',
     *   scopeId: int|null,
     *   scopeName: string,
     *   config: array{
     *     themeScheme: 'light'|'dark'|'auto',
     *     themeColor: string,
     *     themeRadius: int,
     *     headerHeight: int,
     *     siderWidth: int,
     *     layoutMode: string,
     *     darkSider: bool,
     *     tabVisible: bool,
     *     breadcrumbVisible: bool,
     *     multilingualVisible: bool,
     *     globalSearchVisible: bool,
     *     themeConfigVisible: bool
     *   },
     *   version: int
     * }
     */
    public function resetScopeConfig(string $scopeType, ?int $scopeId, string $scopeName, int $actorUserId): array
    {
        $scopeKey = ThemeProfile::scopeKey($scopeType, $scopeId);

        $profile = DB::transaction(function () use ($scopeType, $scopeId, $scopeName, $scopeKey, $actorUserId): ThemeProfile {
            $existing = ThemeProfile::query()
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                return ThemeProfile::query()->create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'scope_key' => $scopeKey,
                    'name' => $scopeName,
                    'status' => '1',
                    'config' => [],
                    'version' => 1,
                    'updated_by' => $actorUserId,
                ]);
            }

            $existing->fill([
                'name' => $scopeName,
                'status' => '1',
                'config' => [],
                'version' => ((int) $existing->version) + 1,
                'updated_by' => $actorUserId,
            ]);
            $existing->save();

            return $existing;
        });

        $this->forgetScopeCache($scopeType, $scopeId);

        return [
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'scopeName' => $scopeName,
            'config' => $this->sanitizeConfig([]),
            'version' => (int) $profile->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{
     *   themeScheme: 'light'|'dark'|'auto',
     *   themeColor: string,
     *   themeRadius: int,
     *   headerHeight: int,
     *   siderWidth: int,
     *   layoutMode: string,
     *   darkSider: bool,
     *   tabVisible: bool,
     *   breadcrumbVisible: bool,
     *   multilingualVisible: bool,
     *   globalSearchVisible: bool,
     *   themeConfigVisible: bool
     * }
     */
    public function sanitizeConfig(array $config): array
    {
        $defaults = $this->defaultConfig();
        $limits = $this->limits();
        $allowedSchemes = $this->allowedSchemes();
        $allowedLayoutModes = $this->allowedLayoutModes();

        $themeScheme = trim((string) ($config['themeScheme'] ?? ''));
        if (! in_array($themeScheme, $allowedSchemes, true)) {
            $themeScheme = $defaults['themeScheme'];
        }

        $themeColor = trim((string) ($config['themeColor'] ?? ''));
        if (! preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $themeColor)) {
            $themeColor = $defaults['themeColor'];
        }

        $themeRadius = $this->normalizeIntValue($config['themeRadius'] ?? null, $defaults['themeRadius'], $limits['themeRadius']);
        $headerHeight = $this->normalizeIntValue($config['headerHeight'] ?? null, $defaults['headerHeight'], $limits['headerHeight']);
        $siderWidth = $this->normalizeIntValue($config['siderWidth'] ?? null, $defaults['siderWidth'], $limits['siderWidth']);
        $siderCollapsedWidth = $this->normalizeIntValue(
            $config['siderCollapsedWidth'] ?? null,
            $defaults['siderCollapsedWidth'],
            $limits['siderCollapsedWidth']
        );
        $footerHeight = $this->normalizeIntValue(
            $config['footerHeight'] ?? null,
            $defaults['footerHeight'],
            $limits['footerHeight']
        );
        $layoutMode = trim((string) ($config['layoutMode'] ?? ''));
        if (! in_array($layoutMode, $allowedLayoutModes, true)) {
            $layoutMode = $defaults['layoutMode'];
        }
        $scrollMode = trim((string) ($config['scrollMode'] ?? ''));
        if (! in_array($scrollMode, $this->allowedScrollModes(), true)) {
            $scrollMode = $defaults['scrollMode'];
        }

        $pageAnimateMode = trim((string) ($config['pageAnimateMode'] ?? ''));
        if (! in_array($pageAnimateMode, $this->allowedPageAnimateModes(), true)) {
            $pageAnimateMode = $defaults['pageAnimateMode'];
        }

        $darkSider = filter_var($config['darkSider'] ?? $defaults['darkSider'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $themeSchemaVisible = filter_var($config['themeSchemaVisible'] ?? $defaults['themeSchemaVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $headerFullscreenVisible = filter_var($config['headerFullscreenVisible'] ?? $defaults['headerFullscreenVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $tabVisible = filter_var($config['tabVisible'] ?? $defaults['tabVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $tabFullscreenVisible = filter_var($config['tabFullscreenVisible'] ?? $defaults['tabFullscreenVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $breadcrumbVisible = filter_var($config['breadcrumbVisible'] ?? $defaults['breadcrumbVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $footerVisible = filter_var($config['footerVisible'] ?? $defaults['footerVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $multilingualVisible = filter_var($config['multilingualVisible'] ?? $defaults['multilingualVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $globalSearchVisible = filter_var($config['globalSearchVisible'] ?? $defaults['globalSearchVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $themeConfigVisible = filter_var($config['themeConfigVisible'] ?? $defaults['themeConfigVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $pageAnimate = filter_var($config['pageAnimate'] ?? $defaults['pageAnimate'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $fixedHeaderAndTab = filter_var($config['fixedHeaderAndTab'] ?? $defaults['fixedHeaderAndTab'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return [
            'themeScheme' => $themeScheme,
            'themeColor' => $themeColor,
            'themeRadius' => $themeRadius,
            'headerHeight' => $headerHeight,
            'siderWidth' => $siderWidth,
            'siderCollapsedWidth' => $siderCollapsedWidth,
            'layoutMode' => $layoutMode,
            'scrollMode' => $scrollMode,
            'darkSider' => $darkSider ?? $defaults['darkSider'],
            'themeSchemaVisible' => $themeSchemaVisible ?? $defaults['themeSchemaVisible'],
            'headerFullscreenVisible' => $headerFullscreenVisible ?? $defaults['headerFullscreenVisible'],
            'tabVisible' => $tabVisible ?? $defaults['tabVisible'],
            'tabFullscreenVisible' => $tabFullscreenVisible ?? $defaults['tabFullscreenVisible'],
            'breadcrumbVisible' => $breadcrumbVisible ?? $defaults['breadcrumbVisible'],
            'footerVisible' => $footerVisible ?? $defaults['footerVisible'],
            'footerHeight' => $footerHeight,
            'multilingualVisible' => $multilingualVisible ?? $defaults['multilingualVisible'],
            'globalSearchVisible' => $globalSearchVisible ?? $defaults['globalSearchVisible'],
            'themeConfigVisible' => $themeConfigVisible ?? $defaults['themeConfigVisible'],
            'pageAnimate' => $pageAnimate ?? $defaults['pageAnimate'],
            'pageAnimateMode' => $pageAnimateMode,
            'fixedHeaderAndTab' => $fixedHeaderAndTab ?? $defaults['fixedHeaderAndTab'],
        ];
    }

    /**
     * @param  array{min: int, max: int}  $limit
     */
    private function normalizeIntValue(mixed $value, int $default, array $limit): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $normalized = (int) $value;
        if ($normalized < $limit['min']) {
            return (int) $limit['min'];
        }

        if ($normalized > $limit['max']) {
            return (int) $limit['max'];
        }

        return $normalized;
    }

    /**
     * @return array{
     *   themeScheme: 'light'|'dark'|'auto',
     *   themeColor: string,
     *   themeRadius: int,
     *   headerHeight: int,
     *   siderWidth: int,
     *   layoutMode: string,
     *   darkSider: bool,
     *   tabVisible: bool,
     *   breadcrumbVisible: bool,
     *   multilingualVisible: bool,
     *   globalSearchVisible: bool,
     *   themeConfigVisible: bool
     * }
     */
    private function defaultConfig(): array
    {
        $configuredDefaults = config('theme.defaults', []);
        $baseDefaults = [
            'themeScheme' => 'light',
            'themeColor' => '#646cff',
            'themeRadius' => 6,
            'headerHeight' => 56,
            'siderWidth' => 220,
            'siderCollapsedWidth' => 64,
            'layoutMode' => 'vertical',
            'scrollMode' => 'content',
            'darkSider' => false,
            'themeSchemaVisible' => true,
            'headerFullscreenVisible' => true,
            'tabVisible' => true,
            'tabFullscreenVisible' => true,
            'breadcrumbVisible' => true,
            'footerVisible' => true,
            'footerHeight' => 48,
            'multilingualVisible' => true,
            'globalSearchVisible' => true,
            'themeConfigVisible' => true,
            'pageAnimate' => true,
            'pageAnimateMode' => 'fade-slide',
            'fixedHeaderAndTab' => true,
        ];

        $defaults = array_replace($baseDefaults, is_array($configuredDefaults) ? $configuredDefaults : []);
        $limits = $this->limits();
        $allowedSchemes = $this->allowedSchemes();
        $allowedLayoutModes = $this->allowedLayoutModes();

        $themeScheme = trim((string) ($defaults['themeScheme'] ?? ''));
        if (! in_array($themeScheme, $allowedSchemes, true)) {
            $themeScheme = $baseDefaults['themeScheme'];
        }

        $themeColor = trim((string) ($defaults['themeColor'] ?? ''));
        if (! preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $themeColor)) {
            $themeColor = $baseDefaults['themeColor'];
        }

        $themeRadius = $this->normalizeIntValue($defaults['themeRadius'] ?? null, (int) $baseDefaults['themeRadius'], $limits['themeRadius']);
        $headerHeight = $this->normalizeIntValue($defaults['headerHeight'] ?? null, (int) $baseDefaults['headerHeight'], $limits['headerHeight']);
        $siderWidth = $this->normalizeIntValue($defaults['siderWidth'] ?? null, (int) $baseDefaults['siderWidth'], $limits['siderWidth']);
        $siderCollapsedWidth = $this->normalizeIntValue(
            $defaults['siderCollapsedWidth'] ?? null,
            (int) $baseDefaults['siderCollapsedWidth'],
            $limits['siderCollapsedWidth']
        );
        $footerHeight = $this->normalizeIntValue(
            $defaults['footerHeight'] ?? null,
            (int) $baseDefaults['footerHeight'],
            $limits['footerHeight']
        );
        $layoutMode = trim((string) ($defaults['layoutMode'] ?? ''));
        if (! in_array($layoutMode, $allowedLayoutModes, true)) {
            $layoutMode = (string) $baseDefaults['layoutMode'];
        }
        $scrollMode = trim((string) ($defaults['scrollMode'] ?? ''));
        if (! in_array($scrollMode, $this->allowedScrollModes(), true)) {
            $scrollMode = (string) $baseDefaults['scrollMode'];
        }

        $pageAnimateMode = trim((string) ($defaults['pageAnimateMode'] ?? ''));
        if (! in_array($pageAnimateMode, $this->allowedPageAnimateModes(), true)) {
            $pageAnimateMode = (string) $baseDefaults['pageAnimateMode'];
        }

        $darkSider = filter_var($defaults['darkSider'] ?? $baseDefaults['darkSider'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $themeSchemaVisible = filter_var($defaults['themeSchemaVisible'] ?? $baseDefaults['themeSchemaVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $headerFullscreenVisible = filter_var($defaults['headerFullscreenVisible'] ?? $baseDefaults['headerFullscreenVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $tabVisible = filter_var($defaults['tabVisible'] ?? $baseDefaults['tabVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $tabFullscreenVisible = filter_var($defaults['tabFullscreenVisible'] ?? $baseDefaults['tabFullscreenVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $breadcrumbVisible = filter_var($defaults['breadcrumbVisible'] ?? $baseDefaults['breadcrumbVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $footerVisible = filter_var($defaults['footerVisible'] ?? $baseDefaults['footerVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $multilingualVisible = filter_var($defaults['multilingualVisible'] ?? $baseDefaults['multilingualVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $globalSearchVisible = filter_var($defaults['globalSearchVisible'] ?? $baseDefaults['globalSearchVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $themeConfigVisible = filter_var($defaults['themeConfigVisible'] ?? $baseDefaults['themeConfigVisible'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $pageAnimate = filter_var($defaults['pageAnimate'] ?? $baseDefaults['pageAnimate'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $fixedHeaderAndTab = filter_var($defaults['fixedHeaderAndTab'] ?? $baseDefaults['fixedHeaderAndTab'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return [
            'themeScheme' => $themeScheme,
            'themeColor' => $themeColor,
            'themeRadius' => $themeRadius,
            'headerHeight' => $headerHeight,
            'siderWidth' => $siderWidth,
            'siderCollapsedWidth' => $siderCollapsedWidth,
            'layoutMode' => $layoutMode,
            'scrollMode' => $scrollMode,
            'darkSider' => $darkSider ?? (bool) $baseDefaults['darkSider'],
            'themeSchemaVisible' => $themeSchemaVisible ?? (bool) $baseDefaults['themeSchemaVisible'],
            'headerFullscreenVisible' => $headerFullscreenVisible ?? (bool) $baseDefaults['headerFullscreenVisible'],
            'tabVisible' => $tabVisible ?? (bool) $baseDefaults['tabVisible'],
            'tabFullscreenVisible' => $tabFullscreenVisible ?? (bool) $baseDefaults['tabFullscreenVisible'],
            'breadcrumbVisible' => $breadcrumbVisible ?? (bool) $baseDefaults['breadcrumbVisible'],
            'footerVisible' => $footerVisible ?? (bool) $baseDefaults['footerVisible'],
            'footerHeight' => $footerHeight,
            'multilingualVisible' => $multilingualVisible ?? (bool) $baseDefaults['multilingualVisible'],
            'globalSearchVisible' => $globalSearchVisible ?? (bool) $baseDefaults['globalSearchVisible'],
            'themeConfigVisible' => $themeConfigVisible ?? (bool) $baseDefaults['themeConfigVisible'],
            'pageAnimate' => $pageAnimate ?? (bool) $baseDefaults['pageAnimate'],
            'pageAnimateMode' => $pageAnimateMode,
            'fixedHeaderAndTab' => $fixedHeaderAndTab ?? (bool) $baseDefaults['fixedHeaderAndTab'],
        ];
    }

    /**
     * @return array{
     *   themeRadius: array{min: int, max: int},
     *   headerHeight: array{min: int, max: int},
     *   siderWidth: array{min: int, max: int},
     *   siderCollapsedWidth: array{min: int, max: int},
     *   footerHeight: array{min: int, max: int}
     * }
     */
    private function limits(): array
    {
        $limits = config('theme.limits', []);

        return [
            'themeRadius' => [
                'min' => (int) ($limits['themeRadius']['min'] ?? 0),
                'max' => (int) ($limits['themeRadius']['max'] ?? 16),
            ],
            'headerHeight' => [
                'min' => (int) ($limits['headerHeight']['min'] ?? 48),
                'max' => (int) ($limits['headerHeight']['max'] ?? 80),
            ],
            'siderWidth' => [
                'min' => (int) ($limits['siderWidth']['min'] ?? 180),
                'max' => (int) ($limits['siderWidth']['max'] ?? 320),
            ],
            'siderCollapsedWidth' => [
                'min' => (int) ($limits['siderCollapsedWidth']['min'] ?? 48),
                'max' => (int) ($limits['siderCollapsedWidth']['max'] ?? 120),
            ],
            'footerHeight' => [
                'min' => (int) ($limits['footerHeight']['min'] ?? 32),
                'max' => (int) ($limits['footerHeight']['max'] ?? 96),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedSchemes(): array
    {
        $schemes = config('theme.allowed_schemes', ['light', 'dark', 'auto']);
        if (! is_array($schemes) || $schemes === []) {
            return ['light', 'dark', 'auto'];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $schemes),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function allowedLayoutModes(): array
    {
        $modes = config('theme.allowed_layout_modes', [
            'vertical',
            'horizontal',
            'vertical-mix',
            'vertical-hybrid-header-first',
            'top-hybrid-sidebar-first',
            'top-hybrid-header-first',
        ]);

        if (! is_array($modes) || $modes === []) {
            return ['vertical'];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $modes),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function allowedScrollModes(): array
    {
        $modes = config('theme.allowed_scroll_modes', ['wrapper', 'content']);
        if (! is_array($modes) || $modes === []) {
            return ['content'];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $modes),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function allowedPageAnimateModes(): array
    {
        $modes = config('theme.allowed_page_animate_modes', [
            'fade',
            'fade-slide',
            'fade-bottom',
            'fade-scale',
            'zoom-fade',
            'zoom-out',
            'none',
        ]);
        if (! is_array($modes) || $modes === []) {
            return ['fade-slide'];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $modes),
            static fn (string $value): bool => $value !== ''
        ));
    }

    /**
     * @return array{config: array<string, mixed>, version: int}
     */
    private function scopePayload(string $scopeType, ?int $scopeId): array
    {
        $cacheKey = $this->scopeCacheKey($scopeType, $scopeId);

        return Cache::remember($cacheKey, now()->addMinutes(10), static function () use ($scopeType, $scopeId): array {
            $profile = ThemeProfile::query()
                ->where('scope_key', ThemeProfile::scopeKey($scopeType, $scopeId))
                ->where('status', '1')
                ->first();

            return [
                'config' => is_array($profile?->config) ? $profile->config : [],
                'version' => (int) ($profile?->version ?? 0),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function diffFromDefault(array $config): array
    {
        $defaults = $this->defaultConfig();
        $stored = [];

        foreach ($config as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }

            if ($defaults[$key] !== $value) {
                $stored[$key] = $value;
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function extractEditableConfig(array $config): array
    {
        $keys = [
            'themeScheme',
            'themeColor',
            'themeRadius',
            'headerHeight',
            'siderWidth',
            'siderCollapsedWidth',
            'layoutMode',
            'scrollMode',
            'darkSider',
            'themeSchemaVisible',
            'headerFullscreenVisible',
            'tabVisible',
            'tabFullscreenVisible',
            'breadcrumbVisible',
            'footerVisible',
            'footerHeight',
            'multilingualVisible',
            'globalSearchVisible',
            'themeConfigVisible',
            'pageAnimate',
            'pageAnimateMode',
            'fixedHeaderAndTab',
        ];
        $payload = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $config)) {
                $payload[$key] = $config[$key];
            }
        }

        return $payload;
    }

    private function forgetScopeCache(string $scopeType, ?int $scopeId): void
    {
        Cache::forget($this->scopeCacheKey($scopeType, $scopeId));
    }

    private function scopeCacheKey(string $scopeType, ?int $scopeId): string
    {
        $normalizedScopeId = $scopeId ?? 0;

        return sprintf('theme.profile.%s.%d', $scopeType, $normalizedScopeId);
    }
}
