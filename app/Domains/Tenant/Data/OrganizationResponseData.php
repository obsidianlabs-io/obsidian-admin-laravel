<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Organization;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final readonly class OrganizationResponseData
{
    public function __construct(
        public int $id,
        public OrganizationSnapshot $snapshot,
        public ?string $version = null,
        public ?string $updateTime = null,
    ) {}

    public static function fromModel(Organization $organization, ?Request $request = null): self
    {
        return new self(
            id: (int) $organization->id,
            snapshot: OrganizationSnapshot::fromModel($organization),
            version: $request instanceof Request
                ? (string) ($organization->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
                : null,
            updateTime: $request instanceof Request
                ? ApiDateTime::formatForRequest($organization->updated_at, $request)
                : null,
        );
    }

    /**
     * @return array{id: int, organizationCode: string, organizationName: string, status: string, sort: int, version?: string, updateTime?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            ...$this->snapshot->toArray(),
        ];

        if ($this->version !== null) {
            $payload['version'] = $this->version;
        }

        if ($this->updateTime !== null) {
            $payload['updateTime'] = $this->updateTime;
        }

        return $payload;
    }
}
