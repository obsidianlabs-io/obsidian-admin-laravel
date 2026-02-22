<?php

declare(strict_types=1);

return [
    'require_email_verification' => filter_var(env('AUTH_REQUIRE_EMAIL_VERIFICATION', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
    'super_admin_require_2fa' => filter_var(env('AUTH_SUPER_ADMIN_REQUIRE_2FA', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
    'totp_window' => env('AUTH_TOTP_WINDOW', 1),
    'totp_replay' => [
        'enabled' => filter_var(env('AUTH_TOTP_REPLAY_PROTECTION', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        'cache_prefix' => env('AUTH_TOTP_REPLAY_CACHE_PREFIX', 'auth:totp:used'),
        'ttl_seconds' => (int) env('AUTH_TOTP_REPLAY_TTL_SECONDS', 0),
    ],
    'auth_tokens' => [
        'single_device_login' => filter_var(env('AUTH_SINGLE_DEVICE_LOGIN', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
    ],
    'proxy_trust' => [
        'proxies' => (string) env('TRUSTED_PROXIES', 'REMOTE_ADDR'),
        'headers' => (string) env('TRUSTED_PROXY_HEADERS', 'DEFAULT'),
    ],
    'optimistic_lock' => [
        'require_token' => (bool) env('OPTIMISTIC_LOCK_REQUIRE_TOKEN', false),
        'token_fields' => ['version', 'updatedAt', 'updateTime'],
    ],

    'baseline' => [
        'minimum_login_max_attempts' => env('SECURITY_BASELINE_MIN_LOGIN_ATTEMPTS', 3),
        'minimum_password_length' => env('SECURITY_BASELINE_MIN_PASSWORD_LENGTH', 8),
        'require_super_admin_2fa' => filter_var(env('SECURITY_BASELINE_REQUIRE_SUPER_ADMIN_2FA', false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,

        /**
         * @var list<string>
         */
        'public_api_route_patterns' => [
            'auth/login',
            'auth/register',
            'auth/forgot-password',
            'auth/reset-password',
            'auth/refreshToken',
            'health',
            'health/live',
            'health/ready',
            'language/locales',
            'language/messages',
            'system/bootstrap',
            'theme/public-config',
        ],

        /**
         * @var list<string>
         */
        'permission_required_route_patterns' => [
            'user/*',
            'role/*',
            'permission/*',
            'tenant/*',
            'language/list',
            'language/options',
            'language',
            'audit/*',
            'theme/config',
            'theme/config/*',
        ],
    ],
];
