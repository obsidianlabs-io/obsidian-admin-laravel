<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class ThemeScopeConfigData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $scopeType,
        public ?int $scopeId,
        public string $scopeName,
        public array $config,
        public int $version,
    ) {}

    /**
     * @return array{scopeType: string, scopeId: int|null, version: int, config: array<string, mixed>}
     */
    public function toAuditArray(): array
    {
        return [
            'scopeType' => $this->scopeType,
            'scopeId' => $this->scopeId,
            'version' => $this->version,
            'config' => $this->config,
        ];
    }
}
