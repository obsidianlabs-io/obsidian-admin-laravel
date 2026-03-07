<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class FeatureFlagListPageData
{
    /**
     * @param  list<FeatureFlagRecordData>  $records
     */
    public function __construct(
        public int $current,
        public int $size,
        public int $total,
        public array $records,
    ) {}

    /**
     * @return array{
     *   current: int,
     *   size: int,
     *   total: int,
     *   records: list<array{
     *     key: string,
     *     enabled: bool,
     *     percentage: int,
     *     platform_only: bool,
     *     tenant_only: bool,
     *     role_codes: list<string>,
     *     global_override: bool|null
     *   }>
     * }
     */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'size' => $this->size,
            'total' => $this->total,
            'records' => array_map(
                static fn (FeatureFlagRecordData $record): array => $record->toArray(),
                $this->records
            ),
        ];
    }
}
