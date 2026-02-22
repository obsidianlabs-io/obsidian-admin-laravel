<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

final class CrudSchemaService
{
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
     * }|null
     */
    public function find(string $resource): ?array
    {
        $resourceKey = trim($resource);
        if ($resourceKey === '') {
            return null;
        }

        /** @var array<string, array<string, mixed>> $resources */
        $resources = config('crud_schema.resources', []);
        $schema = $resources[$resourceKey] ?? null;
        if (! is_array($schema)) {
            return null;
        }

        /** @var array{
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
         * } $schema
         */
        $schema = $schema;

        return $schema;
    }

    public function requiredPermissionCode(string $resource): ?string
    {
        $schema = $this->find($resource);
        if ($schema === null) {
            return null;
        }

        $permission = trim((string) $schema['permission']);

        return $permission !== '' ? $permission : null;
    }
}
