<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Tenant\Models\Tenant;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;

class TenantSeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['tenants']);
    }

    protected function module(): string
    {
        return 'tenant.core';
    }

    /**
     * @return array<int, list<array{code: string, name: string, status: string}>>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::tenants(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{code: string, name: string, status: string}> $tenants */
        $tenants = $payload;
        foreach ($tenants as $tenantData) {
            Tenant::query()->withTrashed()->updateOrCreate(
                ['code' => $tenantData['code']],
                [
                    'name' => $tenantData['name'],
                    'status' => $tenantData['status'],
                    'deleted_at' => null,
                ]
            );
        }
    }
}
