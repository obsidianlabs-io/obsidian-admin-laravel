<?php

declare(strict_types=1);

namespace App\DTOs\Language;

final readonly class ListLanguageTranslationsInputDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public ?string $cursor,
        public string $locale,
        public string $keyword,
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
