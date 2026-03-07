<?php

declare(strict_types=1);

namespace App\DTOs\Permission;

final readonly class ListPermissionsInputDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public ?string $cursor,
        public string $keyword,
        public string $status,
        public string $group
    ) {}

    public function usesCursorPagination(string $paginationMode = ''): bool
    {
        if (strtolower(trim($paginationMode)) === 'cursor') {
            return true;
        }

        return $this->cursor !== null && $this->cursor !== '';
    }
}
