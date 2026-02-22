<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Access\Models\Permission;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;

class PermissionSeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['permissions']);
    }

    protected function module(): string
    {
        return 'rbac.permissions';
    }

    /**
     * @return array<int, list<array{code: string, name: string, group: string, status: string}>>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::permissions(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{code: string, name: string, group: string, status: string}> $permissions */
        $permissions = $payload;
        foreach ($permissions as $permission) {
            Permission::query()->withTrashed()->updateOrCreate(
                ['code' => $permission['code']],
                [
                    'name' => $permission['name'],
                    'group' => $permission['group'],
                    'status' => $permission['status'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
