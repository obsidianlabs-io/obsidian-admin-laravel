<?php

declare(strict_types=1);

namespace App\Domains\Tenant\Data;

use App\Domains\Tenant\Models\Team;

final readonly class TeamSnapshot
{
    public function __construct(
        public int $organizationId,
        public string $teamCode,
        public string $teamName,
        public string $status,
        public int $sort,
    ) {}

    public static function fromModel(Team $team): self
    {
        return new self(
            organizationId: (int) $team->organization_id,
            teamCode: (string) $team->code,
            teamName: (string) $team->name,
            status: (string) $team->status,
            sort: (int) ($team->sort ?? 0),
        );
    }

    /**
     * @return array{organizationId: int, teamCode: string, teamName: string, status: string, sort: int}
     */
    public function toArray(): array
    {
        return [
            'organizationId' => $this->organizationId,
            'teamCode' => $this->teamCode,
            'teamName' => $this->teamName,
            'status' => $this->status,
            'sort' => $this->sort,
        ];
    }
}
