<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Menu Feature Flags
    |--------------------------------------------------------------------------
    |
    | These switches allow enabling/disabling modules without frontend changes.
    | Routes and sidebar menus are both derived from this metadata.
    |
    */
    'features' => [
        'dashboard' => env('MENU_FEATURE_DASHBOARD', true),
        'tenant' => env('MENU_FEATURE_TENANT', true),
        'iam' => env('MENU_FEATURE_IAM', true),
        'role' => env('MENU_FEATURE_ROLE', true),
        'permission' => env('MENU_FEATURE_PERMISSION', true),
        'audit' => env('MENU_FEATURE_AUDIT', true),
        'auditPolicy' => env('MENU_FEATURE_AUDIT_POLICY', true),
        'language' => env('MENU_FEATURE_LANGUAGE', true),
        'theme' => env('MENU_FEATURE_THEME', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Menu Metadata
    |--------------------------------------------------------------------------
    |
    | scope:
    | - both: visible in platform and tenant scopes
    | - platform: visible only when no tenant is selected
    | - tenant: visible only when tenant is selected
    |
    */
    'items' => [
        [
            'key' => 'dashboard',
            'routeKey' => 'dashboard',
            'routePath' => '/dashboard',
            'label' => 'Dashboard',
            'i18nKey' => 'route.dashboard',
            'icon' => 'mdi:monitor-dashboard',
            'order' => 1,
            'scope' => 'both',
            'featureFlag' => 'dashboard',
            'permission' => null,
            'roles' => [],
            'children' => [],
        ],
        [
            'key' => 'tenant',
            'routeKey' => 'tenant',
            'routePath' => '/tenant',
            'label' => 'Tenant',
            'i18nKey' => 'route.tenant',
            'icon' => 'mdi:domain',
            'order' => 2,
            'scope' => 'platform',
            'featureFlag' => 'tenant',
            'permission' => 'tenant.view',
            'roles' => ['R_SUPER'],
            'children' => [],
        ],
        [
            'key' => 'access-management',
            'routeKey' => null,
            'routePath' => null,
            'label' => 'Access Management',
            'i18nKey' => 'menu.accessManagement',
            'icon' => 'mdi:shield-account',
            'order' => 3,
            'scope' => 'both',
            'featureFlag' => 'iam',
            'permission' => [
                'user.view',
                'user.manage',
                'role.view',
                'role.manage',
                'permission.view',
                'permission.manage',
                'language.view',
                'language.manage',
            ],
            'roles' => [],
            'children' => [
                [
                    'key' => 'user',
                    'routeKey' => 'user',
                    'routePath' => '/user',
                    'label' => 'User',
                    'i18nKey' => 'route.user',
                    'icon' => 'mdi:account-multiple-outline',
                    'order' => 1,
                    'scope' => 'both',
                    'featureFlag' => 'iam',
                    'permission' => ['user.view', 'user.manage'],
                    'roles' => [],
                    'children' => [],
                ],
                [
                    'key' => 'role',
                    'routeKey' => 'role',
                    'routePath' => '/role',
                    'label' => 'Role',
                    'i18nKey' => 'route.role',
                    'icon' => 'mdi:account-key-outline',
                    'order' => 2,
                    'scope' => 'both',
                    'featureFlag' => 'role',
                    'permission' => 'role.view',
                    'roles' => [],
                    'children' => [],
                ],
                [
                    'key' => 'permission',
                    'routeKey' => 'permission',
                    'routePath' => '/permission',
                    'label' => 'Permission',
                    'i18nKey' => 'route.permission',
                    'icon' => 'mdi:key-chain-variant',
                    'order' => 4,
                    'scope' => 'platform',
                    'featureFlag' => 'permission',
                    'permission' => 'permission.view',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
            ],
        ],
        [
            'key' => 'platform-settings',
            'routeKey' => null,
            'routePath' => null,
            'label' => 'System Settings',
            'i18nKey' => 'menu.systemSettings',
            'icon' => 'mdi:cog-outline',
            'order' => 4,
            'scope' => 'both',
            'featureFlag' => null,
            'permission' => [
                'theme.view',
                'theme.manage',
                'language.view',
                'language.manage',
                'audit.view',
                'audit.policy.view',
                'audit.policy.manage',
            ],
            'roles' => [],
            'children' => [
                [
                    'key' => 'theme-config',
                    'routeKey' => 'theme-config',
                    'routePath' => '/theme-config',
                    'label' => 'Theme Config',
                    'i18nKey' => 'route.theme-config',
                    'icon' => 'mdi:palette-outline',
                    'order' => 1,
                    'scope' => 'platform',
                    'featureFlag' => 'theme',
                    'permission' => 'theme.view',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
                [
                    'key' => 'language',
                    'routeKey' => 'language',
                    'routePath' => '/language',
                    'label' => 'Localization',
                    'i18nKey' => 'route.language',
                    'icon' => 'mdi:translate',
                    'order' => 2,
                    'scope' => 'platform',
                    'featureFlag' => 'language',
                    'permission' => 'language.view',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
                [
                    'key' => 'audit-policy',
                    'routeKey' => 'audit-policy',
                    'routePath' => '/audit-policy',
                    'label' => 'Audit Policy',
                    'i18nKey' => 'route.audit-policy',
                    'icon' => 'mdi:tune-variant',
                    'order' => 3,
                    'scope' => 'platform',
                    'featureFlag' => 'auditPolicy',
                    'permission' => 'audit.policy.view',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
                [
                    'key' => 'feature-flag',
                    'routeKey' => 'feature-flag',
                    'routePath' => '/feature-flag',
                    'label' => 'Feature Flags',
                    'i18nKey' => 'route.feature-flag',
                    'icon' => 'mdi:toggle-switch-outline',
                    'order' => 4,
                    'scope' => 'platform',
                    'featureFlag' => 'featureFlags',
                    'permission' => 'system.manage',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
                [
                    'key' => 'audit',
                    'routeKey' => 'audit',
                    'routePath' => '/audit',
                    'label' => 'Audit Logs',
                    'i18nKey' => 'route.audit',
                    'icon' => 'mdi:file-search-outline',
                    'order' => 5,
                    'scope' => 'both',
                    'featureFlag' => 'audit',
                    'permission' => 'audit.view',
                    'roles' => ['R_SUPER'],
                    'children' => [],
                ],
            ],
        ],
    ],
];
