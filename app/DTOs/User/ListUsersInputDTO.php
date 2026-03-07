<?php

declare(strict_types=1);

namespace App\DTOs\User;

final readonly class ListUsersInputDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public ?string $cursor,
        public string $keyword,
        public string $userName,
        public string $userEmail,
        public string $roleCode,
        public string $status
    ) {}

    public function usesCursorPagination(string $paginationMode = ''): bool
    {
        if (strtolower(trim($paginationMode)) === 'cursor') {
            return true;
        }

        return $this->cursor !== null && $this->cursor !== '';
    }
}
