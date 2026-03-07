<?php

declare(strict_types=1);

namespace App\DTOs\Audit;

final readonly class UpdateAuditPoliciesInputDTO
{
    /**
     * @param  list<UpdateAuditPolicyRecordInputDTO>  $records
     */
    public function __construct(
        public array $records,
        public string $changeReason
    ) {}
}
