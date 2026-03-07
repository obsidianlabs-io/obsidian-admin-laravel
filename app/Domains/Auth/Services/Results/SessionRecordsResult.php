<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\Results;

final readonly class SessionRecordsResult
{
    /**
     * @param  list<SessionRecord>  $records
     */
    public function __construct(
        private array $records,
    ) {}

    /**
     * @return list<SessionRecord>
     */
    public function records(): array
    {
        return $this->records;
    }
}
