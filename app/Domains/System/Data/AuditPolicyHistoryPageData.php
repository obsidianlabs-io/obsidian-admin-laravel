<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditPolicyHistoryPageData
{
    /**
     * @param  list<AuditPolicyHistoryRecordData>  $records
     */
    public function __construct(
        public int $current,
        public int $size,
        public int $total,
        public array $records,
    ) {}

    /**
     * @return array{
     *   current: int,
     *   size: int,
     *   total: int,
     *   records: list<array{
     *     id: string,
     *     scope: string,
     *     changedByUserId: string,
     *     changedByUserName: string,
     *     changeReason: string,
     *     changedCount: int,
     *     changedActions: list<string>,
     *     createdAt: string
     *   }>
     * }
     */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'size' => $this->size,
            'total' => $this->total,
            'records' => array_map(
                static fn (AuditPolicyHistoryRecordData $record): array => $record->toArray(),
                $this->records
            ),
        ];
    }
}
