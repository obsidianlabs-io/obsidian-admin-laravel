<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Organization;

final readonly class OrganizationSnapshot
{
    public function __construct(
        public string $organizationCode,
        public string $organizationName,
        public string $status,
        public int $sort,
    ) {}

    public static function fromModel(Organization $organization): self
    {
        return new self(
            organizationCode: (string) $organization->code,
            organizationName: (string) $organization->name,
            status: (string) $organization->status,
            sort: (int) ($organization->sort ?? 0),
        );
    }

    /**
     * @return array{organizationCode: string, organizationName: string, status: string, sort: int}
     */
    public function toArray(): array
    {
        return [
            'organizationCode' => $this->organizationCode,
            'organizationName' => $this->organizationName,
            'status' => $this->status,
            'sort' => $this->sort,
        ];
    }
}
