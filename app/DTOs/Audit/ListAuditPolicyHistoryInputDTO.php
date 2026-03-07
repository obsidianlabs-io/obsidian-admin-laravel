<?php

declare(strict_types=1);

namespace App\DTOs\Audit;

final readonly class ListAuditPolicyHistoryInputDTO
{
    public function __construct(
        public int $current,
        public int $size
    ) {}
}
