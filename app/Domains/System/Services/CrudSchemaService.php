<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\System\Data\CrudSchemaData;

final class CrudSchemaService
{
    public function find(string $resource): ?CrudSchemaData
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

        return CrudSchemaData::fromArray($schema);
    }

    public function requiredPermissionCode(string $resource): ?string
    {
        $schema = $this->find($resource);
        if ($schema === null) {
            return null;
        }

        $permission = trim($schema->permission);

        return $permission !== '' ? $permission : null;
    }
}
