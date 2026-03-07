<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyPoliciesResponseData
{
    public function __construct(
        public AuditPolicyRecordsData $records,
    ) {}

    public static function fromRecords(AuditPolicyRecordsData $records): self
    {
        return new self($records);
    }

    /**
     * @return array{records: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'records' => $this->records->toArray(),
        ];
    }
}
