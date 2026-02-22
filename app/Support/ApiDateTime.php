<?php

declare(strict_types=1);

namespace App\Support;

use App\Domains\Access\Models\User;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Http\Request;

final class ApiDateTime
{
    private const DEFAULT_TIMEZONE = 'UTC';

    /**
     * @var array<string, true>|null
     */
    private static ?array $supportedTimezones = null;

    public static function resolveUserTimezone(User $user): string
    {
        $user->loadMissing('preference');

        return self::normalizeTimezone((string) ($user->preference?->timezone ?? ''));
    }

    public static function assignRequestTimezone(Request $request, ?string $timezone): string
    {
        $resolved = self::normalizeTimezone($timezone);
        $request->attributes->set('user_timezone', $resolved);

        return $resolved;
    }

    public static function requestTimezone(Request $request): string
    {
        return self::normalizeTimezone((string) $request->attributes->get('user_timezone', ''));
    }

    public static function normalizeTimezone(?string $timezone): string
    {
        $candidate = trim((string) $timezone);

        if ($candidate === '' || ! self::isSupportedTimezone($candidate)) {
            return self::defaultTimezone();
        }

        return $candidate;
    }

    public static function defaultTimezone(): string
    {
        $configured = trim((string) config('app.timezone', self::DEFAULT_TIMEZONE));

        if ($configured === '' || ! self::isSupportedTimezone($configured)) {
            return self::DEFAULT_TIMEZONE;
        }

        return $configured;
    }

    public static function format(?CarbonInterface $dateTime, ?string $timezone = null): string
    {
        if (! $dateTime) {
            return '';
        }

        return $dateTime->copy()
            ->setTimezone(self::normalizeTimezone($timezone))
            ->format('Y-m-d H:i:s');
    }

    public static function formatForRequest(?CarbonInterface $dateTime, Request $request): string
    {
        return self::format($dateTime, self::requestTimezone($request));
    }

    public static function iso(?CarbonInterface $dateTime, ?string $timezone = null): string
    {
        if (! $dateTime) {
            return '';
        }

        return $dateTime->copy()
            ->setTimezone(self::normalizeTimezone($timezone))
            ->toIso8601String();
    }

    /**
     * @return list<array{timezone: string, offset: string, label: string}>
     */
    public static function listTimezoneOptions(): array
    {
        $nowUtc = now('UTC');
        $records = [];

        foreach (timezone_identifiers_list() as $timezone) {
            $zone = new DateTimeZone($timezone);
            $offsetSeconds = $zone->getOffset($nowUtc);
            $offsetMinutes = (int) floor($offsetSeconds / 60);
            $offsetSign = $offsetMinutes < 0 ? '-' : '+';
            $absoluteMinutes = abs($offsetMinutes);
            $hours = intdiv($absoluteMinutes, 60);
            $minutes = $absoluteMinutes % 60;
            $offset = sprintf('%s%02d:%02d', $offsetSign, $hours, $minutes);

            $records[] = [
                'timezone' => $timezone,
                'offset' => $offset,
                'label' => sprintf('UTC%s %s', $offset, $timezone),
                'offsetMinutes' => $offsetMinutes,
            ];
        }

        usort($records, static function (array $left, array $right): int {
            $leftOffset = (int) ($left['offsetMinutes'] ?? 0);
            $rightOffset = (int) ($right['offsetMinutes'] ?? 0);

            if ($leftOffset !== $rightOffset) {
                return $leftOffset <=> $rightOffset;
            }

            return strcmp((string) ($left['timezone'] ?? ''), (string) ($right['timezone'] ?? ''));
        });

        return array_values(array_map(static function (array $record): array {
            return [
                'timezone' => (string) ($record['timezone'] ?? ''),
                'offset' => (string) ($record['offset'] ?? '+00:00'),
                'label' => (string) ($record['label'] ?? ''),
            ];
        }, $records));
    }

    /**
     * Reset static state between Octane (RoadRunner/Swoole) requests.
     * Register this via AppServiceProvider's octane:requesting lifecycle hook.
     */
    public static function flushState(): void
    {
        self::$supportedTimezones = null;
    }

    private static function isSupportedTimezone(string $timezone): bool
    {
        if (self::$supportedTimezones === null) {
            self::$supportedTimezones = array_fill_keys(timezone_identifiers_list(), true);
        }

        return isset(self::$supportedTimezones[$timezone]);
    }
}
