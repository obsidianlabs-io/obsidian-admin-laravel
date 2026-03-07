<?php

declare(strict_types=1);

namespace App\Domains\Shared\Auth;

final readonly class TenantOptionData
{
    public function __construct(
        public string $tenantId,
        public string $tenantName,
    ) {}

    /**
     * @return array{tenantId: string, tenantName: string}
     */
    public function toArray(): array
    {
        return [
            'tenantId' => $this->tenantId,
            'tenantName' => $this->tenantName,
        ];
    }
}
