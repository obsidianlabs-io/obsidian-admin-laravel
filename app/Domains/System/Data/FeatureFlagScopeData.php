<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class FeatureFlagScopeData
{
    /**
     * @param  list<string>  $roleCodes
     */
    public function __construct(
        public int $tenantId,
        public array $roleCodes,
    ) {}
}
