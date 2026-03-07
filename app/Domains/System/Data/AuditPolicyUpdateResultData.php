<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyUpdateResultData
{
    /**
     * @param  list<AuditPolicyChangeData>  $changes
     */
    public function __construct(
        public int $updated,
        public array $changes,
    ) {}

    /**
     * @return list<string>
     */
    public function changedActions(): array
    {
        return array_values(array_unique(array_map(
            static fn (AuditPolicyChangeData $change): string => $change->action,
            $this->changes
        )));
    }

    /**
     * @return list<array{
     *   action: string,
     *   old: array{enabled: bool, samplingRate: float, retentionDays: int},
     *   new: array{enabled: bool, samplingRate: float, retentionDays: int}
     * }>
     */
    public function changesToArray(): array
    {
        return array_map(
            static fn (AuditPolicyChangeData $change): array => $change->toArray(),
            $this->changes
        );
    }
}
