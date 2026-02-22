<?php

declare(strict_types=1);

return [
    /**
     * Canonical API version prefix.
     */
    'current_version' => env('API_CURRENT_VERSION', 'v1'),

    /**
     * Keep unversioned routes (/api/*) during migration to /api/v1/*.
     */
    'legacy_unversioned_enabled' => (bool) env('API_LEGACY_UNVERSIONED_ENABLED', true),

    'throttle_limit' => (int) env('API_THROTTLE_LIMIT', 60),

    'auth_throttle_limit' => (int) env('AUTH_THROTTLE_LIMIT', 5),

    'auth_menu_cache_ttl_seconds' => (int) env('API_AUTH_MENU_CACHE_TTL_SECONDS', 300),

    'user_profile_cache_ttl_seconds' => (int) env('API_USER_PROFILE_CACHE_TTL_SECONDS', 180),

    'docs' => [
        'cache_enabled' => (bool) env('API_DOCS_CACHE_ENABLED', true),
        'cache_ttl_seconds' => (int) env('API_DOCS_CACHE_TTL_SECONDS', 600),
    ],
];
