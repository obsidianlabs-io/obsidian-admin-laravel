<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyRecordData
{
    public function __construct(
        public string $action,
        public string $category,
        public bool $mandatory,
        public bool $locked,
        public string $lockReason,
        public string $description,
        public AuditPolicyEffectiveStateData $effective,
        public bool $defaultEnabled,
        public float $defaultSamplingRate,
        public int $defaultRetentionDays,
    ) {}

    /**
     * @return array{
     *   action: string,
     *   category: string,
     *   mandatory: bool,
     *   locked: bool,
     *   lockReason: string,
     *   description: string,
     *   enabled: bool,
     *   samplingRate: float,
     *   retentionDays: int,
     *   source: string,
     *   defaultEnabled: bool,
     *   defaultSamplingRate: float,
     *   defaultRetentionDays: int
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'category' => $this->category,
            'mandatory' => $this->mandatory,
            'locked' => $this->locked,
            'lockReason' => $this->lockReason,
            'description' => $this->description,
            'enabled' => $this->effective->enabled,
            'samplingRate' => $this->effective->samplingRate,
            'retentionDays' => $this->effective->retentionDays,
            'source' => $this->effective->source,
            'defaultEnabled' => $this->defaultEnabled,
            'defaultSamplingRate' => $this->defaultSamplingRate,
            'defaultRetentionDays' => $this->defaultRetentionDays,
        ];
    }
}
