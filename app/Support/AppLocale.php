<?php

declare(strict_types=1);

namespace App\Support;

final class AppLocale
{
    public const FRAMEWORK_EN = 'en';

    public const FRAMEWORK_ZH_CN = 'zh_CN';

    public const PREFERRED_EN_US = 'en-US';

    public const PREFERRED_ZH_CN = 'zh-CN';

    public static function defaultFrameworkLocale(): string
    {
        return self::toFrameworkLocale(LocaleDefaults::configured()) ?? self::FRAMEWORK_EN;
    }

    public static function toFrameworkLocale(?string $locale): ?string
    {
        $normalized = self::normalize($locale);
        if ($normalized === null) {
            return null;
        }

        return match (true) {
            $normalized === 'zh', $normalized === 'zh-cn', str_starts_with($normalized, 'zh-') => self::FRAMEWORK_ZH_CN,
            $normalized === 'en', $normalized === 'en-us', str_starts_with($normalized, 'en-') => self::FRAMEWORK_EN,
            default => null,
        };
    }

    public static function toPreferredLocaleCode(?string $locale): ?string
    {
        $normalized = self::normalize($locale);
        if ($normalized === null) {
            return null;
        }

        return match (true) {
            $normalized === 'zh', $normalized === 'zh-cn', $normalized === 'cn', str_starts_with($normalized, 'zh-') => self::PREFERRED_ZH_CN,
            $normalized === 'en', $normalized === 'en-us', str_starts_with($normalized, 'en-') => self::PREFERRED_EN_US,
            default => null,
        };
    }

    private static function normalize(?string $locale): ?string
    {
        $normalized = strtolower(str_replace('_', '-', trim((string) $locale)));

        return $normalized !== '' ? $normalized : null;
    }
}
