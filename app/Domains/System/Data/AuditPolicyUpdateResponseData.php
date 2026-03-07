<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyUpdateResponseData
{
    public function __construct(
        public AuditPolicyGlobalUpdateResultData $result,
        public AuditPolicyRecordsData $records,
    ) {}

    public static function fromResult(AuditPolicyGlobalUpdateResultData $result, AuditPolicyRecordsData $records): self
    {
        return new self($result, $records);
    }

    /**
     * @return array{
     *   updated: int,
     *   clearedTenantOverrides: int,
     *   revisionId: string,
     *   records: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'updated' => $this->result->updated,
            'clearedTenantOverrides' => $this->result->clearedTenantOverrides,
            'revisionId' => $this->result->revisionId,
            'records' => $this->records->toArray(),
        ];
    }
}
