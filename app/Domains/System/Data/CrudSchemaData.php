<?php

declare(strict_types=1);

namespace App\Domains\System\Data;

final readonly class CrudSchemaData
{
    /**
     * @param  list<CrudSchemaSearchFieldData>  $searchFields
     * @param  list<CrudSchemaColumnData>  $columns
     */
    public function __construct(
        public string $resource,
        public string $permission,
        public array $searchFields,
        public array $columns,
        public int $scrollX,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     */
    public static function fromArray(array $schema): self
    {
        $searchFields = array_map(
            static fn (array $field): CrudSchemaSearchFieldData => CrudSchemaSearchFieldData::fromArray($field),
            array_values(array_filter(
                $schema['searchFields'] ?? [],
                static fn (mixed $field): bool => is_array($field),
            )),
        );

        $columns = array_map(
            static fn (array $column): CrudSchemaColumnData => CrudSchemaColumnData::fromArray($column),
            array_values(array_filter(
                $schema['columns'] ?? [],
                static fn (mixed $column): bool => is_array($column),
            )),
        );

        return new self(
            resource: trim((string) ($schema['resource'] ?? '')),
            permission: trim((string) ($schema['permission'] ?? '')),
            searchFields: $searchFields,
            columns: $columns,
            scrollX: max(0, (int) ($schema['scrollX'] ?? 0)),
        );
    }

    /**
     * @return array{
     *   resource: string,
     *   permission: string,
     *   searchFields: list<array{
     *     key: string,
     *     type: string,
     *     labelKey: string,
     *     placeholderKey?: string,
     *     clearable?: bool,
     *     filterable?: bool,
     *     optionSource?: string
     *   }>,
     *   columns: list<array{
     *     key: string,
     *     type: string,
     *     titleKey: string,
     *     align: string,
     *     width?: int,
     *     minWidth?: int,
     *     emptyLabelKey?: string
     *   }>,
     *   scrollX: int
     * }
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resource,
            'permission' => $this->permission,
            'searchFields' => array_map(
                static fn (CrudSchemaSearchFieldData $field): array => $field->toArray(),
                $this->searchFields,
            ),
            'columns' => array_map(
                static fn (CrudSchemaColumnData $column): array => $column->toArray(),
                $this->columns,
            ),
            'scrollX' => $this->scrollX,
        ];
    }
}
