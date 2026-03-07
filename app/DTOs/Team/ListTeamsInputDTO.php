<?php

declare(strict_types=1);

namespace App\DTOs\Team;

final readonly class ListTeamsInputDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public ?string $cursor,
        public string $keyword,
        public string $status,
        public ?int $organizationId
    ) {}

    public function usesCursorPagination(string $paginationMode = ''): bool
    {
        if (strtolower(trim($paginationMode)) === 'cursor') {
            return true;
        }

        return $this->cursor !== null && $this->cursor !== '';
    }
}
