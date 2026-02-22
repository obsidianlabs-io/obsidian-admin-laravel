<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

class TrustedProxyConfig
{
    public static function defaultHeadersMask(): int
    {
        return Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_PREFIX
            | Request::HEADER_X_FORWARDED_AWS_ELB;
    }

    public static function parseHeadersMask(?string $value): ?int
    {
        $normalized = strtoupper(trim((string) $value));

        if ($normalized === '' || $normalized === 'DEFAULT') {
            return self::defaultHeadersMask();
        }

        if ($normalized === 'AWS_ELB' || $normalized === 'HEADER_X_FORWARDED_AWS_ELB') {
            return Request::HEADER_X_FORWARDED_AWS_ELB;
        }

        if ($normalized === 'FORWARDED' || $normalized === 'HEADER_FORWARDED') {
            return Request::HEADER_FORWARDED;
        }

        $mask = 0;
        $parts = preg_split('/[|,]/', $normalized) ?: [];

        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }

            $mask |= match ($token) {
                'X_FORWARDED_FOR', 'HEADER_X_FORWARDED_FOR' => Request::HEADER_X_FORWARDED_FOR,
                'X_FORWARDED_HOST', 'HEADER_X_FORWARDED_HOST' => Request::HEADER_X_FORWARDED_HOST,
                'X_FORWARDED_PORT', 'HEADER_X_FORWARDED_PORT' => Request::HEADER_X_FORWARDED_PORT,
                'X_FORWARDED_PROTO', 'HEADER_X_FORWARDED_PROTO' => Request::HEADER_X_FORWARDED_PROTO,
                'X_FORWARDED_PREFIX', 'HEADER_X_FORWARDED_PREFIX' => Request::HEADER_X_FORWARDED_PREFIX,
                'X_FORWARDED_AWS_ELB', 'HEADER_X_FORWARDED_AWS_ELB', 'AWS_ELB' => Request::HEADER_X_FORWARDED_AWS_ELB,
                'FORWARDED', 'HEADER_FORWARDED' => Request::HEADER_FORWARDED,
                default => 0,
            };
        }

        return $mask > 0 ? $mask : null;
    }

    /**
     * @return list<string>
     */
    public static function describeHeadersMask(int $mask): array
    {
        $headers = [];

        if (($mask & Request::HEADER_FORWARDED) === Request::HEADER_FORWARDED) {
            $headers[] = 'HEADER_FORWARDED';
        }

        if (($mask & Request::HEADER_X_FORWARDED_FOR) === Request::HEADER_X_FORWARDED_FOR) {
            $headers[] = 'HEADER_X_FORWARDED_FOR';
        }

        if (($mask & Request::HEADER_X_FORWARDED_HOST) === Request::HEADER_X_FORWARDED_HOST) {
            $headers[] = 'HEADER_X_FORWARDED_HOST';
        }

        if (($mask & Request::HEADER_X_FORWARDED_PORT) === Request::HEADER_X_FORWARDED_PORT) {
            $headers[] = 'HEADER_X_FORWARDED_PORT';
        }

        if (($mask & Request::HEADER_X_FORWARDED_PROTO) === Request::HEADER_X_FORWARDED_PROTO) {
            $headers[] = 'HEADER_X_FORWARDED_PROTO';
        }

        if (($mask & Request::HEADER_X_FORWARDED_PREFIX) === Request::HEADER_X_FORWARDED_PREFIX) {
            $headers[] = 'HEADER_X_FORWARDED_PREFIX';
        }

        if (($mask & Request::HEADER_X_FORWARDED_AWS_ELB) === Request::HEADER_X_FORWARDED_AWS_ELB) {
            $headers[] = 'HEADER_X_FORWARDED_AWS_ELB';
        }

        return $headers;
    }

    /**
     * @return list<string>
     */
    public static function normalizeProxiesList(?string $value): array
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return [];
        }

        if ($normalized === '*' || $normalized === '**') {
            return [$normalized];
        }

        $parts = array_map(
            static fn (string $item): string => trim($item),
            explode(',', $normalized)
        );

        return array_values(array_filter($parts, static fn (string $item): bool => $item !== ''));
    }
}
