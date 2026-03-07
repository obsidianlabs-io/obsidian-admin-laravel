<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

final class UserAgentParser
{
    public function parse(?string $userAgent, ?string $ipAddress): SessionClientContextData
    {
        $ua = trim((string) ($userAgent ?? ''));
        $ip = trim((string) ($ipAddress ?? ''));

        $deviceType = $this->detectDeviceType($ua);
        $browser = $this->detectBrowser($ua);
        $os = $this->detectOperatingSystem($ua);
        $deviceName = $this->buildDeviceNameFromParts($browser, $os, $deviceType);

        return SessionClientContextData::fromArray([
            'deviceName' => $deviceName,
            'browser' => $browser,
            'os' => $os,
            'deviceType' => $deviceType,
            'ipAddress' => $ip,
        ]);
    }

    public function buildDeviceNameFromParts(?string $browser, ?string $os, ?string $deviceType): ?string
    {
        $resolvedBrowser = SessionClientContextData::sanitize($browser, 40);
        $resolvedOs = SessionClientContextData::sanitize($os, 40);
        $resolvedDeviceType = SessionClientContextData::sanitize($deviceType, 20);

        if ($resolvedBrowser && $resolvedOs) {
            return $resolvedBrowser.' on '.$resolvedOs;
        }

        if ($resolvedBrowser) {
            return $resolvedBrowser;
        }

        if ($resolvedOs && $resolvedDeviceType) {
            return ucfirst($resolvedDeviceType).' ('.$resolvedOs.')';
        }

        if ($resolvedOs) {
            return $resolvedOs;
        }

        if ($resolvedDeviceType) {
            return ucfirst($resolvedDeviceType);
        }

        return null;
    }

    private function detectDeviceType(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider')) {
            return 'bot';
        }

        return 'desktop';
    }

    private function detectBrowser(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'edg/')) {
            return 'Edge';
        }

        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }

        if (str_contains($ua, 'chrome/')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'firefox/')) {
            return 'Firefox';
        }

        if (str_contains($ua, 'safari/')) {
            return 'Safari';
        }

        if (str_contains($ua, 'msie') || str_contains($ua, 'trident/')) {
            return 'Internet Explorer';
        }

        return null;
    }

    private function detectOperatingSystem(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ios') => 'iOS',
            str_contains($ua, 'mac os x'), str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }
}
