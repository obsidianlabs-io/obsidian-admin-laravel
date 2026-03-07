<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class FeatureFlagOverrideResponseData
{
    public function __construct(
        public string $key,
        public ?bool $globalOverride,
    ) {}

    public static function forToggle(string $key, bool $enabled): self
    {
        return new self(
            key: $key,
            globalOverride: $enabled,
        );
    }

    public static function forPurge(string $key): self
    {
        return new self(
            key: $key,
            globalOverride: null,
        );
    }

    /**
     * @return array{key: string, global_override: bool|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'global_override' => $this->globalOverride,
        ];
    }
}
