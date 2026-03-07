<?php

declare(strict_types=1);

namespace App\DTOs\Audit;

final readonly class UpdateAuditPolicyRecordInputDTO
{
    public function __construct(
        public string $action,
        public bool $enabled,
        public float|int|null $samplingRate,
        public ?int $retentionDays
    ) {}
}
