<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\System\Services\ThemeConfigNormalizer;
use Tests\TestCase;

class ThemeConfigNormalizerTest extends TestCase
{
    private ThemeConfigNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ThemeConfigNormalizer;
    }

    public function test_default_config_returns_all_expected_keys(): void
    {
        $config = $this->normalizer->defaultConfig();

        $expectedKeys = [
            'themeScheme', 'themeColor', 'themeRadius', 'headerHeight',
            'siderWidth', 'siderCollapsedWidth', 'layoutMode', 'scrollMode',
            'darkSider', 'themeSchemaVisible', 'headerFullscreenVisible',
            'tabVisible', 'tabFullscreenVisible', 'breadcrumbVisible',
            'footerVisible', 'footerHeight', 'multilingualVisible',
            'globalSearchVisible', 'themeConfigVisible', 'pageAnimate',
            'pageAnimateMode', 'fixedHeaderAndTab',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Default config missing key: {$key}");
        }
    }

    public function test_default_config_has_valid_types(): void
    {
        $config = $this->normalizer->defaultConfig();

        $this->assertIsString($config['themeScheme']);
        $this->assertContains($config['themeScheme'], ['light', 'dark', 'auto']);
        $this->assertIsString($config['themeColor']);
        $this->assertIsInt($config['themeRadius']);
        $this->assertIsInt($config['headerHeight']);
        $this->assertIsInt($config['siderWidth']);
        $this->assertIsInt($config['siderCollapsedWidth']);
        $this->assertIsString($config['layoutMode']);
        $this->assertIsString($config['scrollMode']);
        $this->assertIsBool($config['darkSider']);
        $this->assertIsBool($config['pageAnimate']);
        $this->assertIsBool($config['fixedHeaderAndTab']);
    }

    public function test_sanitize_config_fills_missing_keys_with_defaults(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([]);

        $defaults = $this->normalizer->defaultConfig();

        $this->assertSame($defaults, $sanitized);
    }

    public function test_sanitize_config_clamps_int_values_to_limits(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'themeRadius' => 999,
            'headerHeight' => 1,
            'siderWidth' => 500,
        ]);

        $this->assertLessThanOrEqual(16, $sanitized['themeRadius']);
        $this->assertGreaterThanOrEqual(48, $sanitized['headerHeight']);
        $this->assertLessThanOrEqual(320, $sanitized['siderWidth']);
    }

    public function test_sanitize_config_rejects_invalid_theme_color(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'themeColor' => 'not-a-color',
        ]);

        $defaults = $this->normalizer->defaultConfig();
        $this->assertSame($defaults['themeColor'], $sanitized['themeColor']);
    }

    public function test_sanitize_config_accepts_valid_hex_color(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'themeColor' => '#ff0000',
        ]);

        $this->assertSame('#ff0000', $sanitized['themeColor']);
    }

    public function test_sanitize_config_accepts_8_digit_hex_color(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'themeColor' => '#ff0000ff',
        ]);

        $this->assertSame('#ff0000ff', $sanitized['themeColor']);
    }

    public function test_sanitize_config_rejects_invalid_layout_mode(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'layoutMode' => 'invalid-mode',
        ]);

        $defaults = $this->normalizer->defaultConfig();
        $this->assertSame($defaults['layoutMode'], $sanitized['layoutMode']);
    }

    public function test_sanitize_config_rejects_invalid_scroll_mode(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'scrollMode' => 'invalid',
        ]);

        $defaults = $this->normalizer->defaultConfig();
        $this->assertSame($defaults['scrollMode'], $sanitized['scrollMode']);
    }

    public function test_sanitize_config_rejects_invalid_page_animate_mode(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'pageAnimateMode' => 'invalid',
        ]);

        $defaults = $this->normalizer->defaultConfig();
        $this->assertSame($defaults['pageAnimateMode'], $sanitized['pageAnimateMode']);
    }

    public function test_sanitize_config_normalizes_boolean_values(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'darkSider' => 1,
            'tabVisible' => 'true',
            'pageAnimate' => 0,
        ]);

        $this->assertTrue($sanitized['darkSider']);
        $this->assertTrue($sanitized['tabVisible']);
        $this->assertFalse($sanitized['pageAnimate']);
    }

    public function test_sanitize_config_handles_null_boolean_values(): void
    {
        $sanitized = $this->normalizer->sanitizeConfig([
            'darkSider' => null,
        ]);

        $defaults = $this->normalizer->defaultConfig();
        $this->assertSame($defaults['darkSider'], $sanitized['darkSider']);
    }

    public function test_normalize_theme_scheme_returns_valid_scheme(): void
    {
        $this->assertSame('light', $this->normalizer->normalizeThemeScheme('light', 'dark'));
        $this->assertSame('dark', $this->normalizer->normalizeThemeScheme('dark', 'light'));
        $this->assertSame('auto', $this->normalizer->normalizeThemeScheme('auto', 'light'));
    }

    public function test_normalize_theme_scheme_returns_fallback_for_invalid(): void
    {
        $this->assertSame('dark', $this->normalizer->normalizeThemeScheme('invalid', 'dark'));
        $this->assertSame('light', $this->normalizer->normalizeThemeScheme('', 'light'));
    }

    public function test_diff_from_default_returns_only_changed_keys(): void
    {
        $defaults = $this->normalizer->defaultConfig();
        $modified = array_merge($defaults, ['themeColor' => '#ff0000']);

        $diff = $this->normalizer->diffFromDefault($modified);

        $this->assertArrayHasKey('themeColor', $diff);
        $this->assertSame('#ff0000', $diff['themeColor']);
        $this->assertArrayNotHasKey('themeScheme', $diff);
        $this->assertArrayNotHasKey('layoutMode', $diff);
    }

    public function test_diff_from_default_ignores_unknown_keys(): void
    {
        $diff = $this->normalizer->diffFromDefault([
            'unknownKey' => 'value',
            'themeColor' => '#ff0000',
        ]);

        $this->assertArrayNotHasKey('unknownKey', $diff);
        $this->assertArrayHasKey('themeColor', $diff);
    }

    public function test_diff_from_default_returns_empty_when_all_default(): void
    {
        $defaults = $this->normalizer->defaultConfig();
        $diff = $this->normalizer->diffFromDefault($defaults);

        $this->assertEmpty($diff);
    }

    public function test_extract_editable_config_filters_to_known_keys(): void
    {
        $extracted = $this->normalizer->extractEditableConfig([
            'themeColor' => '#ff0000',
            'unknownKey' => 'value',
            'layoutMode' => 'horizontal',
        ]);

        $this->assertArrayHasKey('themeColor', $extracted);
        $this->assertArrayHasKey('layoutMode', $extracted);
        $this->assertArrayNotHasKey('unknownKey', $extracted);
    }

    public function test_extract_editable_config_returns_empty_for_empty_input(): void
    {
        $extracted = $this->normalizer->extractEditableConfig([]);

        $this->assertEmpty($extracted);
    }

    public function test_allowed_schemes_contains_light_dark_auto(): void
    {
        $schemes = $this->normalizer->allowedSchemes();

        $this->assertContains('light', $schemes);
        $this->assertContains('dark', $schemes);
        $this->assertContains('auto', $schemes);
    }
}
