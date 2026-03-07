<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class ApiAccessLogPruneResultData
{
    public function __construct(
        public bool $dryRun,
        public int $retentionDays,
        public int $totalDeleted,
    ) {}

    /**
     * @return array{
     *   dryRun: bool,
     *   retentionDays: int,
     *   totalDeleted: int
     * }
     */
    public function toArray(): array
    {
        return [
            'dryRun' => $this->dryRun,
            'retentionDays' => $this->retentionDays,
            'totalDeleted' => $this->totalDeleted,
        ];
    }
}
