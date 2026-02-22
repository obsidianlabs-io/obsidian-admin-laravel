<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\System\Models\Language;

final class LocaleDefaults
{
    public static function configured(): string
    {
        $configured = trim((string) config('i18n.default_locale', 'en-US'));

        return $configured !== '' ? $configured : 'en-US';
    }

    public static function resolve(): string
    {
        $configured = self::configured();

        $configuredIsActive = Language::query()
            ->where('status', '1')
            ->where('code', $configured)
            ->exists();

        if ($configuredIsActive) {
            return $configured;
        }

        $databaseDefault = Language::query()
            ->where('status', '1')
            ->orderByDesc('is_default')
            ->orderBy('sort')
            ->orderBy('id')
            ->value('code');

        if (is_string($databaseDefault) && trim($databaseDefault) !== '') {
            return $databaseDefault;
        }

        return $configured;
    }
}
