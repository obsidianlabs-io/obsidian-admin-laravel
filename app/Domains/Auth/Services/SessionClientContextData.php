<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

final readonly class SessionClientContextData
{
    public function __construct(
        public ?string $deviceName = null,
        public ?string $deviceAlias = null,
        public ?string $browser = null,
        public ?string $os = null,
        public ?string $deviceType = null,
        public ?string $ipAddress = null,
    ) {}

    /**
     * @param  array{
     *   deviceName?: mixed,
     *   deviceAlias?: mixed,
     *   browser?: mixed,
     *   os?: mixed,
     *   deviceType?: mixed,
     *   ipAddress?: mixed
     * }  $value
     */
    public static function fromArray(array $value): self
    {
        return new self(
            deviceName: self::sanitize($value['deviceName'] ?? null, 80),
            deviceAlias: self::sanitize($value['deviceAlias'] ?? null, 80),
            browser: self::sanitize($value['browser'] ?? null, 40),
            os: self::sanitize($value['os'] ?? null, 40),
            deviceType: self::sanitize($value['deviceType'] ?? null, 20),
            ipAddress: self::sanitize($value['ipAddress'] ?? null, 64),
        );
    }

    public function withDeviceAlias(?string $alias): self
    {
        return new self(
            deviceName: $this->deviceName,
            deviceAlias: self::sanitize($alias, 80),
            browser: $this->browser,
            os: $this->os,
            deviceType: $this->deviceType,
            ipAddress: $this->ipAddress,
        );
    }

    public function merge(self $override): self
    {
        return self::fromArray(array_merge($this->toArray(), $override->toArray()));
    }

    /**
     * @return array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }
     */
    public function toArray(): array
    {
        return array_filter([
            'deviceName' => $this->deviceName,
            'deviceAlias' => $this->deviceAlias,
            'browser' => $this->browser,
            'os' => $this->os,
            'deviceType' => $this->deviceType,
            'ipAddress' => $this->ipAddress,
        ], static fn (mixed $item): bool => is_string($item) && $item !== '');
    }

    public static function sanitize(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > $maxLength) {
            $normalized = substr($normalized, 0, $maxLength);
        }

        $normalized = trim($normalized);

        return $normalized !== '' ? $normalized : null;
    }
}
