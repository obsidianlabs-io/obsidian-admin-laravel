<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Services\FeatureFlagService;

class MenuMetadataService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly ApiCacheService $apiCacheService
    ) {}

    /**
     * @var array<string, string>
     */
    private const MENU_I18N_FALLBACK_BY_KEY = [
        'dashboard' => 'route.dashboard',
        'tenant' => 'route.tenant',
        'user' => 'route.user',
        'role' => 'route.role',
        'audit' => 'route.audit',
        'audit-policy' => 'route.audit-policy',
        'permission' => 'route.permission',
        'language' => 'route.language',
        'theme-config' => 'route.theme-config',
        'access-management' => 'menu.accessManagement',
        'platform-settings' => 'menu.systemSettings',
    ];

    /**
     * @param  list<string>  $roleCodes
     * @param  list<string>  $permissionCodes
     * @return array{
     *   menuScope: 'platform'|'tenant',
     *   menus: list<array<string, mixed>>,
     *   routeRules: array<string, array{
     *      enabled: bool,
     *      permissions: list<string>,
     *      roles: list<string>,
     *      noTenantOnly: bool,
     *      tenantOnly: bool
     *   }>
     * }
     */
    public function resolveForUser(User $user, ?int $tenantId, array $roleCodes, array $permissionCodes): array
    {
        $normalizedRoleCodes = $this->normalizeStringList($roleCodes);
        sort($normalizedRoleCodes);

        $normalizedPermissionCodes = $this->normalizeStringList($permissionCodes);
        sort($normalizedPermissionCodes);

        $signature = sprintf(
            'user:%d|tenant:%s|roles:%s|permissions:%s|v_roles:%d|v_permissions:%d|v_features:%d|v_tenants:%d',
            (int) $user->id,
            $tenantId === null ? 'none' : (string) $tenantId,
            implode(',', $normalizedRoleCodes),
            implode(',', $normalizedPermissionCodes),
            $this->apiCacheService->version('roles'),
            $this->apiCacheService->version('permissions'),
            $this->apiCacheService->version('features'),
            $this->apiCacheService->version('tenants')
        );

        /** @var array{
         *   menuScope: 'platform'|'tenant',
         *   menus: list<array<string, mixed>>,
         *   routeRules: array<string, array{
         *      enabled: bool,
         *      permissions: list<string>,
         *      roles: list<string>,
         *      noTenantOnly: bool,
         *      tenantOnly: bool
         *   }>
         * } $resolved
         */
        $resolved = $this->apiCacheService->remember(
            namespace: 'auth.menu',
            signature: $signature,
            resolver: function () use ($tenantId, $normalizedRoleCodes, $normalizedPermissionCodes): array {
                $items = $this->menuItems();

                return [
                    'menuScope' => $tenantId === null ? 'platform' : 'tenant',
                    'menus' => $this->filterMenuItems($items, $tenantId, $normalizedRoleCodes, $normalizedPermissionCodes),
                    'routeRules' => $this->buildRouteRules($items, $tenantId, $normalizedRoleCodes),
                ];
            },
            ttlSeconds: max(1, (int) config('api.auth_menu_cache_ttl_seconds', 300))
        );

        return $resolved;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function menuItems(): array
    {
        $items = config('menu.items', []);

        return is_array($items) ? $items : [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $roleCodes
     * @param  list<string>  $permissionCodes
     * @return list<array<string, mixed>>
     */
    private function filterMenuItems(array $items, ?int $tenantId, array $roleCodes, array $permissionCodes): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $children = $this->filterMenuItems(
                $this->normalizeChildren($item['children'] ?? []),
                $tenantId,
                $roleCodes,
                $permissionCodes
            );

            $isVisible = $this->isMenuItemVisible($item, $tenantId, $roleCodes, $permissionCodes);

            if (! $isVisible && $children === []) {
                continue;
            }

            $routeKey = trim((string) ($item['routeKey'] ?? ''));
            if ($routeKey === '' && $children === []) {
                continue;
            }

            $resolvedI18nKey = $this->resolveMenuI18nKey($item);

            $filtered[] = [
                'key' => (string) ($item['key'] ?? ''),
                'routeKey' => $routeKey,
                'routePath' => (string) ($item['routePath'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'i18nKey' => $resolvedI18nKey,
                'icon' => isset($item['icon']) ? (string) $item['icon'] : null,
                'order' => (int) ($item['order'] ?? 0),
                'scope' => (string) ($item['scope'] ?? 'both'),
                'featureFlag' => isset($item['featureFlag']) ? (string) $item['featureFlag'] : null,
                'children' => $children,
            ];
        }

        usort($filtered, static fn (array $a, array $b): int => ((int) $a['order']) <=> ((int) $b['order']));

        return array_values($filtered);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $roleCodes
     * @param  list<string>  $permissionCodes
     */
    private function isMenuItemVisible(array $item, ?int $tenantId, array $roleCodes, array $permissionCodes): bool
    {
        if (! $this->isFeatureEnabled($item, $tenantId, $roleCodes)) {
            return false;
        }

        if (! $this->isScopeAllowed((string) ($item['scope'] ?? 'both'), $tenantId)) {
            return false;
        }

        $requiredRoles = $this->normalizeStringList($item['roles'] ?? []);
        if ($requiredRoles !== [] && array_intersect($requiredRoles, $roleCodes) === []) {
            return false;
        }

        $requiredPermissions = $this->normalizePermissionList($item['permission'] ?? []);
        if ($requiredPermissions !== [] && array_intersect($requiredPermissions, $permissionCodes) === []) {
            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $roleCodes
     * @return array<string, array{
     *    enabled: bool,
     *    permissions: list<string>,
     *    roles: list<string>,
     *    noTenantOnly: bool,
     *    tenantOnly: bool
     * }>
     */
    private function buildRouteRules(array $items, ?int $tenantId, array $roleCodes): array
    {
        $rules = [];
        $this->fillRouteRules($rules, $items, $tenantId, $roleCodes);

        return $rules;
    }

    /**
     * @param array<string, array{
     *    enabled: bool,
     *    permissions: list<string>,
     *    roles: list<string>,
     *    noTenantOnly: bool,
     *    tenantOnly: bool
     * }> $rules
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $roleCodes
     */
    private function fillRouteRules(array &$rules, array $items, ?int $tenantId, array $roleCodes): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $routeKey = trim((string) ($item['routeKey'] ?? ''));
            $scope = (string) ($item['scope'] ?? 'both');
            $requiredPermissions = $this->normalizePermissionList($item['permission'] ?? []);

            if ($routeKey !== '') {
                $rules[$routeKey] = [
                    'enabled' => $this->isFeatureEnabled($item, $tenantId, $roleCodes),
                    'permissions' => $requiredPermissions,
                    'roles' => $this->normalizeStringList($item['roles'] ?? []),
                    'noTenantOnly' => $scope === 'platform',
                    'tenantOnly' => $scope === 'tenant',
                ];
            }

            $this->fillRouteRules($rules, $this->normalizeChildren($item['children'] ?? []), $tenantId, $roleCodes);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeChildren(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var list<array<string, mixed>> $children */
        $children = array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));

        return $children;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(static fn (mixed $item): string => trim((string) $item), $value),
                static fn (string $item): bool => $item !== ''
            )
        );
    }

    /**
     * @return list<string>
     */
    private function normalizePermissionList(mixed $value): array
    {
        if (is_string($value)) {
            $permission = trim($value);

            return $permission === '' ? [] : [$permission];
        }

        return $this->normalizeStringList($value);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveMenuI18nKey(array $item): ?string
    {
        $i18nKey = trim((string) ($item['i18nKey'] ?? ''));
        if ($i18nKey !== '') {
            return $i18nKey;
        }

        $itemKey = trim((string) ($item['key'] ?? ''));
        if ($itemKey !== '' && isset(self::MENU_I18N_FALLBACK_BY_KEY[$itemKey])) {
            return self::MENU_I18N_FALLBACK_BY_KEY[$itemKey];
        }

        $routeKey = trim((string) ($item['routeKey'] ?? ''));
        if ($routeKey !== '' && isset(self::MENU_I18N_FALLBACK_BY_KEY[$routeKey])) {
            return self::MENU_I18N_FALLBACK_BY_KEY[$routeKey];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $roleCodes
     */
    private function isFeatureEnabled(array $item, ?int $tenantId, array $roleCodes): bool
    {
        $flag = trim((string) ($item['featureFlag'] ?? ''));
        if ($flag === '') {
            return true;
        }

        return $this->featureFlagService->isMenuFeatureEnabled($flag, $tenantId, $roleCodes);
    }

    private function isScopeAllowed(string $scope, ?int $tenantId): bool
    {
        return match ($scope) {
            'platform' => $tenantId === null,
            'tenant' => $tenantId !== null,
            default => true,
        };
    }
}
