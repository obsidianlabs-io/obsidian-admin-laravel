<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Definitions (Pennant)
    |--------------------------------------------------------------------------
    |
    | Safe rollout rules by tenant / role with deterministic percentage release.
    | Scope context is generated as:
    |   tenant:{id-or-0}|roles:{ROLE1,ROLE2}
    |
    */
    'definitions' => [
        'menu.dashboard' => [
            'enabled' => filter_var(env('FF_MENU_DASHBOARD', env('MENU_FEATURE_DASHBOARD', true)), FILTER_VALIDATE_BOOLEAN),
            'percentage' => (int) env('FF_MENU_DASHBOARD_PERCENTAGE', 100),
        ],
        'menu.tenant' => [
            'enabled' => filter_var(env('FF_MENU_TENANT', env('MENU_FEATURE_TENANT', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_TENANT_PERCENTAGE', 100),
        ],
        'menu.iam' => [
            'enabled' => filter_var(env('FF_MENU_IAM', env('MENU_FEATURE_IAM', true)), FILTER_VALIDATE_BOOLEAN),
            'percentage' => (int) env('FF_MENU_IAM_PERCENTAGE', 100),
        ],
        'menu.role' => [
            'enabled' => filter_var(env('FF_MENU_ROLE', env('MENU_FEATURE_ROLE', true)), FILTER_VALIDATE_BOOLEAN),
            'percentage' => (int) env('FF_MENU_ROLE_PERCENTAGE', 100),
        ],
        'menu.permission' => [
            'enabled' => filter_var(env('FF_MENU_PERMISSION', env('MENU_FEATURE_PERMISSION', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_PERMISSION_PERCENTAGE', 100),
        ],
        'menu.audit' => [
            'enabled' => filter_var(env('FF_MENU_AUDIT', env('MENU_FEATURE_AUDIT', true)), FILTER_VALIDATE_BOOLEAN),
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_AUDIT_PERCENTAGE', 100),
        ],
        'menu.auditPolicy' => [
            'enabled' => filter_var(env('FF_MENU_AUDIT_POLICY', env('MENU_FEATURE_AUDIT_POLICY', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_AUDIT_POLICY_PERCENTAGE', 100),
        ],
        'menu.language' => [
            'enabled' => filter_var(env('FF_MENU_LANGUAGE', env('MENU_FEATURE_LANGUAGE', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_LANGUAGE_PERCENTAGE', 100),
        ],
        'menu.theme' => [
            'enabled' => filter_var(env('FF_MENU_THEME', env('MENU_FEATURE_THEME', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_THEME_PERCENTAGE', 100),
        ],
        'menu.featureFlags' => [
            'enabled' => filter_var(env('FF_MENU_FEATURE_FLAGS', env('MENU_FEATURE_FEATURE_FLAGS', true)), FILTER_VALIDATE_BOOLEAN),
            'platform_only' => true,
            'role_codes' => ['R_SUPER'],
            'percentage' => (int) env('FF_MENU_FEATURE_FLAGS_PERCENTAGE', 100),
        ],
    ],
];
