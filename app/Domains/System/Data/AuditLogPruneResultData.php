<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class AuditLogPruneResultData
{
    public function __construct(
        public bool $dryRun,
        public int $totalDeleted,
        public int $unknownDeleted,
        public int $actionCount,
    ) {}

    /**
     * @return array{
     *   dryRun: bool,
     *   totalDeleted: int,
     *   unknownDeleted: int,
     *   actionCount: int
     * }
     */
    public function toArray(): array
    {
        return [
            'dryRun' => $this->dryRun,
            'totalDeleted' => $this->totalDeleted,
            'unknownDeleted' => $this->unknownDeleted,
            'actionCount' => $this->actionCount,
        ];
    }
}
