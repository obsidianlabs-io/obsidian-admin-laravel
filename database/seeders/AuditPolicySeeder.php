<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\System\Models\AuditPolicy;
use Database\Seeders\Support\SeedCatalog;
use Database\Seeders\Support\VersionedSeeder;

class AuditPolicySeeder extends VersionedSeeder
{
    /**
     * @return list<string>
     */
    protected function requiredTables(): array
    {
        return array_merge(parent::requiredTables(), ['audit_policies']);
    }

    protected function module(): string
    {
        return 'audit.policies';
    }

    /**
     * @return array<int, list<array{action: string, enabled: bool, retention_days: int}>>
     */
    protected function versionedPayloads(): array
    {
        return [
            1 => SeedCatalog::auditPolicies(),
        ];
    }

    protected function applyVersion(int $version, mixed $payload): void
    {
        unset($version);

        /** @var list<array{action: string, enabled: bool, retention_days: int}> $policies */
        $policies = $payload;
        foreach ($policies as $policy) {
            AuditPolicy::query()->updateOrCreate(
                [
                    'tenant_scope_id' => AuditPolicy::PLATFORM_SCOPE_ID,
                    'action' => $policy['action'],
                ],
                [
                    'tenant_id' => null,
                    'is_mandatory' => false,
                    'enabled' => $policy['enabled'],
                    'sampling_rate' => 1.0,
                    'retention_days' => $policy['retention_days'],
                ]
            );
        }
    }
}
