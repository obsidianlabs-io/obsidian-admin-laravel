<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyEffectiveStateData
{
    public function __construct(
        public bool $enabled,
        public float $samplingRate,
        public int $retentionDays,
        public string $source = 'default',
    ) {}

    /**
     * @return array{
     *   enabled: bool,
     *   samplingRate: float,
     *   retentionDays: int,
     *   source: string
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'samplingRate' => $this->samplingRate,
            'retentionDays' => $this->retentionDays,
            'source' => $this->source,
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   samplingRate: float,
     *   retentionDays: int
     * }
     */
    public function toRuleArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'samplingRate' => $this->samplingRate,
            'retentionDays' => $this->retentionDays,
        ];
    }
}
