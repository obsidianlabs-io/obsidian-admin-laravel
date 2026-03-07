<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyRecordsData
{
    /**
     * @param  list<AuditPolicyRecordData>  $records
     */
    public function __construct(
        public array $records,
    ) {}

    /**
     * @return list<array{
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
     * }>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (AuditPolicyRecordData $record): array => $record->toArray(),
            $this->records
        );
    }
}
