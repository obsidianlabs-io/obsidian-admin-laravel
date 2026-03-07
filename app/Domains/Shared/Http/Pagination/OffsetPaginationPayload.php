<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Pagination;

final readonly class OffsetPaginationPayload
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $current,
        public int $size,
        public int $total,
        public array $records,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->extra, [
            'current' => $this->current,
            'size' => $this->size,
            'total' => $this->total,
            'records' => $this->records,
        ]);
    }
}
