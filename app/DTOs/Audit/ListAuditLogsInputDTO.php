<?php

declare(strict_types=1);

namespace App\DTOs\Audit;

final readonly class ListAuditLogsInputDTO
{
    public function __construct(
        public int $current,
        public int $size,
        public ?string $cursor,
        public string $keyword,
        public string $action,
        public string $logType,
        public string $userName,
        public string $requestId,
        public string $dateFrom,
        public string $dateTo
    ) {}

    public function usesCursorPagination(string $paginationMode = ''): bool
    {
        if (strtolower(trim($paginationMode)) === 'cursor') {
            return true;
        }

        return $this->cursor !== null && $this->cursor !== '';
    }
}
