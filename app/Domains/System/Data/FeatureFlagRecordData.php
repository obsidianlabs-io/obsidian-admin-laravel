<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class FeatureFlagRecordData
{
    /**
     * @param  list<string>  $roleCodes
     */
    public function __construct(
        public string $key,
        public bool $enabled,
        public int $percentage,
        public bool $platformOnly,
        public bool $tenantOnly,
        public array $roleCodes,
        public ?bool $globalOverride,
    ) {}

    /**
     * @return array{
     *   key: string,
     *   enabled: bool,
     *   percentage: int,
     *   platform_only: bool,
     *   tenant_only: bool,
     *   role_codes: list<string>,
     *   global_override: bool|null
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'enabled' => $this->enabled,
            'percentage' => $this->percentage,
            'platform_only' => $this->platformOnly,
            'tenant_only' => $this->tenantOnly,
            'role_codes' => $this->roleCodes,
            'global_override' => $this->globalOverride,
        ];
    }
}
