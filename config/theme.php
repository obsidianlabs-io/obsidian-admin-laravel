<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Theme Runtime Defaults
    |--------------------------------------------------------------------------
    |
    | These defaults are applied first. Runtime overrides can then be applied
    | per scope (platform / tenant) from database without frontend redeploy.
    |
    */
    'defaults' => [
        'themeScheme' => (string) env('APP_THEME_SCHEME', 'light'),
        'themeColor' => (string) env('APP_THEME_COLOR', '#646cff'),
        'themeRadius' => (int) env('APP_THEME_RADIUS', 6),
        'headerHeight' => (int) env('APP_THEME_HEADER_HEIGHT', 56),
        'siderWidth' => (int) env('APP_THEME_SIDER_WIDTH', 220),
        'siderCollapsedWidth' => (int) env('APP_THEME_SIDER_COLLAPSED_WIDTH', 64),
        'layoutMode' => (string) env('APP_THEME_LAYOUT_MODE', 'vertical'),
        'scrollMode' => (string) env('APP_THEME_SCROLL_MODE', 'content'),
        'darkSider' => filter_var(env('APP_THEME_DARK_SIDER', false), FILTER_VALIDATE_BOOLEAN),
        'themeSchemaVisible' => filter_var(env('APP_THEME_SCHEMA_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'headerFullscreenVisible' => filter_var(env('APP_THEME_HEADER_FULLSCREEN_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'tabVisible' => filter_var(env('APP_THEME_TAB_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'tabFullscreenVisible' => filter_var(env('APP_THEME_TAB_FULLSCREEN_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'breadcrumbVisible' => filter_var(env('APP_THEME_BREADCRUMB_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'footerVisible' => filter_var(env('APP_THEME_FOOTER_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'footerHeight' => (int) env('APP_THEME_FOOTER_HEIGHT', 48),
        'multilingualVisible' => filter_var(env('APP_THEME_MULTILINGUAL_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'globalSearchVisible' => filter_var(env('APP_THEME_GLOBAL_SEARCH_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'themeConfigVisible' => filter_var(env('APP_THEME_CONFIG_VISIBLE', true), FILTER_VALIDATE_BOOLEAN),
        'pageAnimate' => filter_var(env('APP_THEME_PAGE_ANIMATE', true), FILTER_VALIDATE_BOOLEAN),
        'pageAnimateMode' => (string) env('APP_THEME_PAGE_ANIMATE_MODE', 'fade-slide'),
        'fixedHeaderAndTab' => filter_var(env('APP_THEME_FIXED_HEADER_AND_TAB', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Runtime Limits
    |--------------------------------------------------------------------------
    |
    | Guardrails for platform and tenant-level runtime updates.
    |
    */
    'limits' => [
        'themeRadius' => ['min' => 0, 'max' => 16],
        'headerHeight' => ['min' => 48, 'max' => 80],
        'siderWidth' => ['min' => 180, 'max' => 320],
        'siderCollapsedWidth' => ['min' => 48, 'max' => 120],
        'footerHeight' => ['min' => 32, 'max' => 96],
    ],

    /**
     * @var list<string>
     */
    'allowed_schemes' => ['light', 'dark', 'auto'],

    /**
     * @var list<string>
     */
    'allowed_layout_modes' => [
        'vertical',
        'horizontal',
        'vertical-mix',
        'vertical-hybrid-header-first',
        'top-hybrid-sidebar-first',
        'top-hybrid-header-first',
    ],

    /**
     * @var list<string>
     */
    'allowed_scroll_modes' => ['wrapper', 'content'],

    /**
     * @var list<string>
     */
    'allowed_page_animate_modes' => ['fade', 'fade-slide', 'fade-bottom', 'fade-scale', 'zoom-fade', 'zoom-out', 'none'],
];
