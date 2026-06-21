<?php

declare(strict_types=1);

namespace App\Domains\Shared\Http\Controllers\Concerns;

use App\Domains\Shared\Http\Pagination\CursorPaginationPayload;
use App\Domains\Shared\Http\Pagination\OffsetPaginationPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait PaginatesApiQueries
{
    /**
     * @param  array{size: int, hasMore: bool, nextCursor: string}  $page
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $extra
     */
    protected function cursorPaginationPayload(array $page, array $records, array $extra = []): CursorPaginationPayload
    {
        return new CursorPaginationPayload(
            size: $page['size'],
            hasMore: $page['hasMore'],
            nextCursor: $page['nextCursor'],
            records: $records,
            extra: $extra,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $extra
     */
    protected function offsetPaginationPayload(
        int $current,
        int $size,
        int $total,
        array $records,
        array $extra = [],
    ): OffsetPaginationPayload {
        return new OffsetPaginationPayload(
            current: $current,
            size: $size,
            total: $total,
            records: $records,
            extra: $extra,
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{
     *   records: Collection<int, TModel>,
     *   size: int,
     *   hasMore: bool,
     *   nextCursor: string
     * }
     */
    protected function cursorPaginateById(
        Builder $query,
        int $size,
        ?string $cursorToken = null,
        bool $descending = true
    ): array {
        $size = max(1, min(100, $size));
        $cursorId = $this->decodeCursorId($cursorToken);
        $operator = $descending ? '<' : '>';
        $direction = $descending ? 'desc' : 'asc';

        if ($cursorId !== null) {
            $query->where('id', $operator, $cursorId);
        }

        $records = $query
            ->orderBy('id', $direction)
            ->limit($size + 1)
            ->get();
        /** @var Collection<int, TModel> $records */
        $records = $records;

        $hasMore = $records->count() > $size;
        if ($hasMore) {
            $records = $records->take($size)->values();
            /** @var Collection<int, TModel> $records */
            $records = $records;
        }

        $lastModel = $records->last();
        $nextCursor = '';
        if ($hasMore && $lastModel) {
            $lastId = (int) ($lastModel->getAttribute('id') ?? 0);
            if ($lastId > 0) {
                $nextCursor = $this->encodeCursorId($lastId);
            }
        }

        return [
            'records' => $records,
            'size' => $size,
            'hasMore' => $hasMore,
            'nextCursor' => $nextCursor,
        ];
    }

    private function decodeCursorId(?string $token): ?int
    {
        $raw = trim((string) $token);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $raw) === 1) {
            $value = (int) $raw;

            return $value > 0 ? $value : null;
        }

        $normalized = strtr($raw, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        if (! preg_match('/^\d+$/', $decoded)) {
            return null;
        }

        $value = (int) $decoded;

        return $value > 0 ? $value : null;
    }

    private function encodeCursorId(int $id): string
    {
        return rtrim(strtr(base64_encode((string) max(1, $id)), '+/', '-_'), '=');
    }
}
