<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Team;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final readonly class TeamResponseData
{
    public function __construct(
        public int $id,
        public TeamSnapshot $snapshot,
        public ?string $version = null,
        public ?string $updateTime = null,
    ) {}

    public static function fromModel(Team $team, ?Request $request = null): self
    {
        return new self(
            id: (int) $team->id,
            snapshot: TeamSnapshot::fromModel($team),
            version: $request instanceof Request
                ? (string) ($team->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
                : null,
            updateTime: $request instanceof Request
                ? ApiDateTime::formatForRequest($team->updated_at, $request)
                : null,
        );
    }

    /**
     * @return array{id: int, organizationId: string, teamCode: string, teamName: string, status: string, sort: int, version?: string, updateTime?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'id' => $this->id,
            'organizationId' => (string) $this->snapshot->organizationId,
            'teamCode' => $this->snapshot->teamCode,
            'teamName' => $this->snapshot->teamName,
            'status' => $this->snapshot->status,
            'sort' => $this->snapshot->sort,
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
