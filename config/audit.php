<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Retention Defaults
    |--------------------------------------------------------------------------
    |
    | Retention can be overridden per action in audit policy settings.
    |
    */
    'retention' => [
        'mandatory_days' => (int) env('AUDIT_RETENTION_MANDATORY_DAYS', 365),
        'optional_days' => (int) env('AUDIT_RETENTION_OPTIONAL_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Sampling Defaults
    |--------------------------------------------------------------------------
    |
    | Sampling applies to optional events only. Mandatory events are always
    | stored with sampling rate 1.0.
    |
    */
    'sampling' => [
        'default_optional_rate' => (float) env('AUDIT_OPTIONAL_DEFAULT_SAMPLING_RATE', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Queue Delivery
    |--------------------------------------------------------------------------
    |
    | Audit writes can be pushed to queue for lower API latency under load.
    | Keep sync delivery as automatic fallback when queue dispatch fails.
    |
    */
    'queue' => [
        'enabled' => (bool) env('AUDIT_QUEUE_ENABLED', true),
        'connection' => env('AUDIT_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'name' => env('AUDIT_QUEUE_NAME', 'audit'),
        'tries' => (int) env('AUDIT_QUEUE_TRIES', 5),
        'backoff' => array_values(array_filter(array_map(
            static fn (string $value): int => max(1, (int) trim($value)),
            explode(',', (string) env('AUDIT_QUEUE_BACKOFF', '5,30,120'))
        ))),
        'timeout' => (int) env('AUDIT_QUEUE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auditable Events Catalog
    |--------------------------------------------------------------------------
    |
    | category:
    | - mandatory: cannot be disabled, always sampled at 1.0
    | - optional: can be configured by policy
    |
    */
    'events' => [
        'auth.login' => [
            'category' => 'optional',
            'description' => 'User login',
            'default_enabled' => false,
            'default_retention_days' => 30,
            'default_sampling_rate' => 1.0,
        ],
        'auth.logout' => [
            'category' => 'optional',
            'description' => 'User logout',
            'default_enabled' => false,
            'default_retention_days' => 30,
            'default_sampling_rate' => 1.0,
        ],

        // User security and lifecycle
        'user.register' => ['category' => 'mandatory', 'description' => 'User account registration'],
        'user.verify_email' => ['category' => 'mandatory', 'description' => 'User email verification'],
        'user.2fa.enable' => ['category' => 'mandatory', 'description' => 'Enable two-factor authentication'],
        'user.2fa.disable' => ['category' => 'mandatory', 'description' => 'Disable two-factor authentication'],
        'user.assign_role' => ['category' => 'mandatory', 'description' => 'Assign role to user'],
        'user.create' => ['category' => 'mandatory', 'description' => 'Create user'],
        'user.update' => ['category' => 'mandatory', 'description' => 'Update user'],
        'user.delete' => ['category' => 'mandatory', 'description' => 'Delete user'],
        'user.profile.update' => ['category' => 'mandatory', 'description' => 'Update own profile'],

        // Noisy preference event: disabled by default
        'user.locale.update' => [
            'category' => 'optional',
            'description' => 'Update preferred language',
            'default_enabled' => false,
            'default_retention_days' => 30,
            'default_sampling_rate' => 1.0,
        ],
        'user.preferences.update' => [
            'category' => 'optional',
            'description' => 'Update user preferences',
            'default_enabled' => false,
            'default_retention_days' => 30,
            'default_sampling_rate' => 1.0,
        ],

        // Role and permission management
        'role.create' => ['category' => 'mandatory', 'description' => 'Create role'],
        'role.update' => ['category' => 'mandatory', 'description' => 'Update role'],
        'role.delete' => ['category' => 'mandatory', 'description' => 'Delete role'],
        'role.sync_permissions' => ['category' => 'mandatory', 'description' => 'Sync role permissions'],
        'permission.create' => ['category' => 'mandatory', 'description' => 'Create permission'],
        'permission.update' => ['category' => 'mandatory', 'description' => 'Update permission'],
        'permission.delete' => ['category' => 'mandatory', 'description' => 'Delete permission'],

        // Tenant management
        'tenant.create' => ['category' => 'mandatory', 'description' => 'Create tenant'],
        'tenant.update' => ['category' => 'mandatory', 'description' => 'Update tenant'],
        'tenant.delete' => ['category' => 'mandatory', 'description' => 'Delete tenant'],

        // Localization content management
        'language.translation.create' => ['category' => 'optional', 'description' => 'Create language translation'],
        'language.translation.update' => ['category' => 'optional', 'description' => 'Update language translation'],
        'language.translation.delete' => ['category' => 'optional', 'description' => 'Delete language translation'],

        // Audit system operations
        'audit.policy.update' => ['category' => 'mandatory', 'description' => 'Update audit policy'],
        'system.config.update' => ['category' => 'mandatory', 'description' => 'Update system configuration'],
        'theme.config.update' => ['category' => 'mandatory', 'description' => 'Update theme configuration'],
        'theme.config.reset' => ['category' => 'mandatory', 'description' => 'Reset theme configuration'],
    ],
];
