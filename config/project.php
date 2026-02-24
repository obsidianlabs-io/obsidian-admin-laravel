<?php

declare(strict_types=1);

return [
    'default_profile' => env('PROJECT_PROFILE', 'base'),

    /**
     * Profile installer catalog for new projects.
     *
     * env:
     *   Env overrides that can be written into `.env` by the installer.
     * audit_overrides:
     *   Global audit policy overrides (tenant scope = platform default).
     */
    'profiles' => [
        'base' => [
            'description' => 'Balanced baseline for most multi-tenant internal admin systems.',
            'env' => [
                'AUTH_REQUIRE_EMAIL_VERIFICATION' => false,
                'AUTH_SUPER_ADMIN_REQUIRE_2FA' => false,
                'LOG_HTTP_REQUESTS' => true,
                'MENU_FEATURE_DASHBOARD' => true,
                'MENU_FEATURE_TENANT' => true,
                'MENU_FEATURE_IAM' => true,
                'MENU_FEATURE_ROLE' => true,
                'MENU_FEATURE_PERMISSION' => true,
                'MENU_FEATURE_AUDIT' => true,
                'MENU_FEATURE_AUDIT_POLICY' => true,
                'MENU_FEATURE_LANGUAGE' => true,
                'MENU_FEATURE_THEME' => true,
                'APP_THEME_LAYOUT_MODE' => 'vertical',
                'APP_THEME_MULTILINGUAL_VISIBLE' => true,
                'APP_THEME_GLOBAL_SEARCH_VISIBLE' => true,
                'APP_THEME_CONFIG_VISIBLE' => true,
            ],
            'audit_overrides' => [
                'auth.login' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 60],
                'auth.logout' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 60],
                'user.locale.update' => ['enabled' => false, 'samplingRate' => 1, 'retentionDays' => 30],
                'user.preferences.update' => ['enabled' => false, 'samplingRate' => 1, 'retentionDays' => 30],
            ],
        ],

        'strict-enterprise' => [
            'description' => 'Security and compliance first profile for regulated workloads.',
            'env' => [
                'AUTH_REQUIRE_EMAIL_VERIFICATION' => true,
                'AUTH_SUPER_ADMIN_REQUIRE_2FA' => true,
                'LOG_HTTP_REQUESTS' => true,
                'MENU_FEATURE_DASHBOARD' => true,
                'MENU_FEATURE_TENANT' => true,
                'MENU_FEATURE_IAM' => true,
                'MENU_FEATURE_ROLE' => true,
                'MENU_FEATURE_PERMISSION' => true,
                'MENU_FEATURE_AUDIT' => true,
                'MENU_FEATURE_AUDIT_POLICY' => true,
                'MENU_FEATURE_LANGUAGE' => true,
                'MENU_FEATURE_THEME' => true,
                'APP_THEME_LAYOUT_MODE' => 'vertical',
                'APP_THEME_MULTILINGUAL_VISIBLE' => true,
                'APP_THEME_GLOBAL_SEARCH_VISIBLE' => true,
                'APP_THEME_CONFIG_VISIBLE' => false,
            ],
            'audit_overrides' => [
                'auth.login' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 180],
                'auth.logout' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 180],
                'user.locale.update' => ['enabled' => false, 'samplingRate' => 1, 'retentionDays' => 30],
                'user.preferences.update' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 90],
                'theme.config.update' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 365],
                'theme.config.reset' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 365],
            ],
        ],

        'lean-support' => [
            'description' => 'Low-noise profile optimized for customer support and operations teams.',
            'env' => [
                'AUTH_REQUIRE_EMAIL_VERIFICATION' => false,
                'AUTH_SUPER_ADMIN_REQUIRE_2FA' => true,
                'LOG_HTTP_REQUESTS' => false,
                'MENU_FEATURE_DASHBOARD' => true,
                'MENU_FEATURE_TENANT' => true,
                'MENU_FEATURE_IAM' => true,
                'MENU_FEATURE_ROLE' => true,
                'MENU_FEATURE_PERMISSION' => false,
                'MENU_FEATURE_AUDIT' => true,
                'MENU_FEATURE_AUDIT_POLICY' => true,
                'MENU_FEATURE_LANGUAGE' => true,
                'MENU_FEATURE_THEME' => false,
                'APP_THEME_LAYOUT_MODE' => 'vertical',
                'APP_THEME_MULTILINGUAL_VISIBLE' => true,
                'APP_THEME_GLOBAL_SEARCH_VISIBLE' => false,
                'APP_THEME_CONFIG_VISIBLE' => false,
            ],
            'audit_overrides' => [
                'auth.login' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 30],
                'auth.logout' => ['enabled' => true, 'samplingRate' => 1, 'retentionDays' => 30],
                'user.locale.update' => ['enabled' => false, 'samplingRate' => 1, 'retentionDays' => 14],
                'user.preferences.update' => ['enabled' => false, 'samplingRate' => 1, 'retentionDays' => 14],
            ],
        ],
    ],
];
