<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Pagination;

final readonly class CursorPaginationPayload
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $size,
        public bool $hasMore,
        public string $nextCursor,
        public array $records,
        public array $extra = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge($this->extra, [
            'paginationMode' => 'cursor',
            'size' => $this->size,
            'hasMore' => $this->hasMore,
            'nextCursor' => $this->nextCursor,
            'records' => $this->records,
        ]);
    }
}
