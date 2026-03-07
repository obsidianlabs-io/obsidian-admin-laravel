<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyChangeData
{
    public function __construct(
        public string $action,
        public AuditPolicyEffectiveStateData $old,
        public AuditPolicyEffectiveStateData $new,
    ) {}

    /**
     * @return array{
     *   action: string,
     *   old: array{enabled: bool, samplingRate: float, retentionDays: int},
     *   new: array{enabled: bool, samplingRate: float, retentionDays: int}
     * }
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'old' => $this->old->toRuleArray(),
            'new' => $this->new->toRuleArray(),
        ];
    }
}
