<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class ThemeConfigResponseData
{
    public function __construct(
        public ThemeScopeConfigData $scopeConfig,
        public EffectiveThemeConfigData $effectiveConfig,
        public bool $editable,
    ) {}

    /**
     * @return array{
     *   scopeType: string,
     *   scopeId: string,
     *   scopeName: string,
     *   version: int,
     *   config: array<string, mixed>,
     *   effectiveConfig: array<string, mixed>,
     *   effectiveVersion: int,
     *   editable: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'scopeType' => $this->scopeConfig->scopeType,
            'scopeId' => $this->scopeConfig->scopeId !== null ? (string) $this->scopeConfig->scopeId : '',
            'scopeName' => $this->scopeConfig->scopeName,
            'version' => $this->scopeConfig->version,
            'config' => $this->scopeConfig->config,
            'effectiveConfig' => $this->effectiveConfig->config,
            'effectiveVersion' => $this->effectiveConfig->profileVersion,
            'editable' => $this->editable,
        ];
    }
}
