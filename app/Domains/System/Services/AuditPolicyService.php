<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Access\Models\User;
use App\Domains\System\Data\AuditLogPruneResultData;
use App\Domains\System\Data\AuditPolicyChangeData;
use App\Domains\System\Data\AuditPolicyEffectiveStateData;
use App\Domains\System\Data\AuditPolicyGlobalUpdateResultData;
use App\Domains\System\Data\AuditPolicyHistoryPageData;
use App\Domains\System\Data\AuditPolicyHistoryRecordData;
use App\Domains\System\Data\AuditPolicyRecordData;
use App\Domains\System\Data\AuditPolicyRecordsData;
use App\Domains\System\Data\AuditPolicyUpdateResultData;
use App\Domains\System\Models\AuditLog;
use App\Domains\System\Models\AuditPolicy;
use App\Domains\System\Models\AuditPolicyRevision;
use App\DTOs\Audit\UpdateAuditPolicyRecordInputDTO;
use App\Support\ApiDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AuditPolicyService
{
    /**
     * @var array<int, array<string, AuditPolicy>>
     */
    private array $scopePoliciesCache = [];

    /**
     * @var array<string, array{
     *   action: string,
     *   category: 'mandatory'|'optional',
     *   mandatory: bool,
     *   description: string,
     *   defaultEnabled: bool,
     *   defaultSamplingRate: float,
     *   defaultRetentionDays: int
     * }>
     */
    private ?array $eventCatalogCache = null;

    public function listEffectivePolicies(?int $tenantId): AuditPolicyRecordsData
    {
        $records = [];
        $catalog = $this->eventCatalog();

        foreach ($catalog as $action => $definition) {
            $effective = $this->resolveEffectivePolicy($action, $tenantId);
            $records[] = new AuditPolicyRecordData(
                action: $action,
                category: $definition['category'],
                mandatory: $definition['mandatory'],
                locked: $definition['mandatory'],
                lockReason: $definition['mandatory']
                    ? 'Mandatory events are always enabled and cannot be sampled.'
                    : '',
                description: $definition['description'],
                effective: $effective,
                defaultEnabled: $definition['defaultEnabled'],
                defaultSamplingRate: $definition['defaultSamplingRate'],
                defaultRetentionDays: $definition['defaultRetentionDays'],
            );
        }

        usort($records, static function (AuditPolicyRecordData $left, AuditPolicyRecordData $right): int {
            $leftCategoryRank = $left->category === 'mandatory' ? 0 : 1;
            $rightCategoryRank = $right->category === 'mandatory' ? 0 : 1;

            if ($leftCategoryRank !== $rightCategoryRank) {
                return $leftCategoryRank <=> $rightCategoryRank;
            }

            return strcmp($left->action, $right->action);
        });

        return new AuditPolicyRecordsData($records);
    }

    public function listRevisionHistory(int $current = 1, int $size = 10, ?string $timezone = null): AuditPolicyHistoryPageData
    {
        $current = max(1, $current);
        $size = max(1, min(100, $size));
        $resolvedTimezone = ApiDateTime::normalizeTimezone($timezone);

        $query = AuditPolicyRevision::query()
            ->where('scope', 'global')
            ->with('changedByUser:id,name');

        $total = $query->count();

        $records = [];

        $revisions = $query->orderByDesc('id')
            ->forPage($current, $size)
            ->get();

        foreach ($revisions as $revision) {
            $changedByUser = $revision->getRelationValue('changedByUser');
            $changedByUserName = $changedByUser instanceof User ? (string) $changedByUser->name : 'System';

            $records[] = new AuditPolicyHistoryRecordData(
                id: (string) $revision->id,
                scope: (string) $revision->scope,
                changedByUserId: $revision->changed_by_user_id ? (string) $revision->changed_by_user_id : '',
                changedByUserName: $changedByUserName,
                changeReason: (string) $revision->change_reason,
                changedCount: (int) $revision->changed_count,
                changedActions: $this->normalizeActionList($revision->changed_actions),
                createdAt: ApiDateTime::iso($revision->created_at, $resolvedTimezone),
            );
        }

        return new AuditPolicyHistoryPageData(
            current: $current,
            size: $size,
            total: $total,
            records: $records,
        );
    }

    /**
     * @param  list<UpdateAuditPolicyRecordInputDTO>  $records
     */
    public function updatePolicies(?int $tenantId, array $records): AuditPolicyUpdateResultData
    {
        $catalog = $this->eventCatalog();
        $scopeId = $tenantId ?? AuditPolicy::PLATFORM_SCOPE_ID;

        $existingPolicies = AuditPolicy::query()
            ->where('tenant_scope_id', $scopeId)
            ->get()
            ->keyBy(static fn (AuditPolicy $policy): string => (string) $policy->action);

        $changes = [];

        foreach ($records as $record) {
            $action = trim($record->action);
            if ($action === '') {
                throw new InvalidArgumentException('Action is required');
            }

            $definition = $catalog[$action] ?? null;
            if (! $definition) {
                throw new InvalidArgumentException("Unknown audit action: {$action}");
            }

            $isMandatory = (bool) $definition['mandatory'];
            $oldEffective = $this->resolveEffectivePolicy($action, $tenantId);
            $oldValues = $oldEffective->toRuleArray();
            $enabled = $record->enabled;
            $samplingRate = $this->normalizeSamplingRate($record->samplingRate ?? $definition['defaultSamplingRate']);
            $retentionDays = $this->normalizeRetentionDays($record->retentionDays ?? $definition['defaultRetentionDays']);

            if ($isMandatory && ! $enabled) {
                throw new InvalidArgumentException("Mandatory audit action cannot be disabled: {$action}");
            }

            if ($isMandatory) {
                $enabled = true;
                $samplingRate = 1.0;
                $retentionDays = $oldValues['retentionDays'];
            }

            /** @var AuditPolicy|null $existing */
            $existing = $existingPolicies->get($action);
            if (
                ! $existing
                && $oldValues === [
                    'enabled' => $enabled,
                    'samplingRate' => $samplingRate,
                    'retentionDays' => $retentionDays,
                ]
            ) {
                continue;
            }

            if (
                $existing
                && (bool) $existing->enabled === $enabled
                && $this->normalizeSamplingRate($existing->sampling_rate) === $samplingRate
                && $this->normalizeRetentionDays($existing->retention_days ?? $retentionDays) === $retentionDays
                && (bool) $existing->is_mandatory === $isMandatory
            ) {
                continue;
            }

            $updated = AuditPolicy::query()->updateOrCreate(
                [
                    'tenant_scope_id' => $scopeId,
                    'action' => $action,
                ],
                [
                    'tenant_id' => $tenantId,
                    'is_mandatory' => $isMandatory,
                    'enabled' => $enabled,
                    'sampling_rate' => $samplingRate,
                    'retention_days' => $retentionDays,
                ]
            );

            $newValues = new AuditPolicyEffectiveStateData(
                enabled: (bool) $updated->enabled,
                samplingRate: $this->normalizeSamplingRate($updated->sampling_rate),
                retentionDays: $this->normalizeRetentionDays((int) ($updated->retention_days ?? $retentionDays)),
            );

            if ($oldValues !== $newValues->toRuleArray()) {
                $changes[] = new AuditPolicyChangeData(
                    action: $action,
                    old: new AuditPolicyEffectiveStateData(
                        enabled: $oldValues['enabled'],
                        samplingRate: $oldValues['samplingRate'],
                        retentionDays: $oldValues['retentionDays'],
                    ),
                    new: $newValues,
                );
            }
        }

        $this->flushCaches();

        return new AuditPolicyUpdateResultData(
            updated: count($changes),
            changes: $changes,
        );
    }

    /**
     * Update global policies and clear tenant overrides for affected actions.
     *
     * @param  list<UpdateAuditPolicyRecordInputDTO>  $records
     */
    public function updateGlobalPolicies(
        array $records,
        ?int $changedByUserId = null,
        ?string $changeReason = null
    ): AuditPolicyGlobalUpdateResultData {
        $normalizedReason = trim((string) $changeReason);

        return DB::transaction(function () use ($records, $changedByUserId, $normalizedReason): AuditPolicyGlobalUpdateResultData {
            $result = $this->updatePolicies(null, $records);
            $actions = $result->changedActions();

            $clearedTenantOverrides = 0;
            if ($actions !== []) {
                $clearedTenantOverrides = (int) AuditPolicy::query()
                    ->whereNotNull('tenant_id')
                    ->whereIn('action', $actions)
                    ->delete();
            }

            $this->flushCaches();
            $snapshot = $this->listEffectivePolicies(null);

            $revisionId = '';
            if ($result->updated > 0) {
                if ($normalizedReason === '') {
                    throw new InvalidArgumentException('Change reason is required when updating audit policy');
                }

                $changedActions = $result->changedActions();

                $revision = AuditPolicyRevision::query()->create([
                    'scope' => 'global',
                    'changed_by_user_id' => $changedByUserId,
                    'change_reason' => $normalizedReason,
                    'changed_count' => count($result->changes),
                    'changed_actions' => $changedActions,
                    'changes' => $result->changesToArray(),
                    'policy_snapshot' => $snapshot->toArray(),
                ]);

                $revisionId = (string) $revision->id;
            }

            return new AuditPolicyGlobalUpdateResultData(
                updated: $result->updated,
                clearedTenantOverrides: $clearedTenantOverrides,
                revisionId: $revisionId,
                changes: $result->changes,
            );
        });
    }

    public function shouldLog(string $action, ?int $tenantId): bool
    {
        $effective = $this->resolveEffectivePolicy($action, $tenantId);

        if (! $effective->enabled) {
            return false;
        }

        $samplingRate = $effective->samplingRate;
        if ($samplingRate >= 1.0) {
            return true;
        }

        if ($samplingRate <= 0.0) {
            return false;
        }

        $threshold = (int) floor($samplingRate * 10000);

        return random_int(1, 10000) <= max(0, min(10000, $threshold));
    }

    public function pruneExpiredLogs(bool $dryRun = false): AuditLogPruneResultData
    {
        $catalog = $this->eventCatalog();
        $knownActions = array_keys($catalog);
        $now = now();
        $totalDeleted = 0;

        foreach ($catalog as $action => $definition) {
            $retentionDays = $this->resolveEffectivePolicy($action, null)->retentionDays;
            $cutoff = $now->copy()->subDays($retentionDays);

            $deleted = $this->runPruneQuery(
                AuditLog::query()
                    ->where('action', $action)
                    ->where('created_at', '<', $cutoff),
                $dryRun
            );
            $totalDeleted += $deleted;
        }

        $unknownCutoff = $now->copy()->subDays($this->optionalDefaultRetentionDays());
        $unknownQuery = AuditLog::query()->where('created_at', '<', $unknownCutoff);
        if ($knownActions !== []) {
            $unknownQuery->whereNotIn('action', $knownActions);
        }

        $unknownDeleted = $this->runPruneQuery($unknownQuery, $dryRun);
        $totalDeleted += $unknownDeleted;

        return new AuditLogPruneResultData(
            dryRun: $dryRun,
            totalDeleted: $totalDeleted,
            unknownDeleted: $unknownDeleted,
            actionCount: count($knownActions),
        );
    }

    /**
     * @return array{
     *   action: string,
     *   category: 'mandatory'|'optional',
     *   mandatory: bool,
     *   description: string,
     *   defaultEnabled: bool,
     *   defaultSamplingRate: float,
     *   defaultRetentionDays: int
     * }|null
     */
    private function eventDefinition(string $action): ?array
    {
        return $this->eventCatalog()[$action] ?? null;
    }

    /**
     * @return array<string, array{
     *   action: string,
     *   category: 'mandatory'|'optional',
     *   mandatory: bool,
     *   description: string,
     *   defaultEnabled: bool,
     *   defaultSamplingRate: float,
     *   defaultRetentionDays: int
     * }>
     */
    private function eventCatalog(): array
    {
        if ($this->eventCatalogCache !== null) {
            return $this->eventCatalogCache;
        }

        $catalog = [];
        $rawEvents = config('audit.events', []);
        if (! is_array($rawEvents)) {
            $rawEvents = [];
        }

        foreach ($rawEvents as $action => $raw) {
            $actionName = trim((string) $action);
            if ($actionName === '' || ! is_array($raw)) {
                continue;
            }

            $category = (string) ($raw['category'] ?? 'optional');
            $category = $category === 'mandatory' ? 'mandatory' : 'optional';
            $mandatory = $category === 'mandatory';

            $defaultEnabled = $mandatory
                ? true
                : filter_var($raw['default_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $defaultSamplingRate = $mandatory
                ? 1.0
                : $this->normalizeSamplingRate($raw['default_sampling_rate'] ?? $this->optionalDefaultSamplingRate());
            $defaultRetentionDays = $this->normalizeRetentionDays(
                $raw['default_retention_days'] ?? ($mandatory ? $this->mandatoryDefaultRetentionDays() : $this->optionalDefaultRetentionDays())
            );

            $catalog[$actionName] = [
                'action' => $actionName,
                'category' => $category,
                'mandatory' => $mandatory,
                'description' => trim((string) ($raw['description'] ?? '')),
                'defaultEnabled' => $defaultEnabled,
                'defaultSamplingRate' => $defaultSamplingRate,
                'defaultRetentionDays' => $defaultRetentionDays,
            ];
        }

        $this->eventCatalogCache = $catalog;

        return $this->eventCatalogCache;
    }

    private function resolveEffectivePolicy(string $action, ?int $tenantId): AuditPolicyEffectiveStateData
    {
        $definition = $this->eventDefinition($action);
        if (! $definition) {
            return new AuditPolicyEffectiveStateData(
                enabled: true,
                samplingRate: 1.0,
                retentionDays: $this->optionalDefaultRetentionDays(),
                source: 'default',
            );
        }

        if ($definition['mandatory']) {
            $retentionDays = $definition['defaultRetentionDays'];
            $source = 'default';

            $platformPolicy = $this->scopePolicies(null)[$action] ?? null;
            $tenantPolicy = $tenantId !== null ? ($this->scopePolicies($tenantId)[$action] ?? null) : null;
            $selectedPolicy = $tenantPolicy ?? $platformPolicy;

            if ($selectedPolicy instanceof AuditPolicy) {
                $retentionDays = $this->normalizeRetentionDays($selectedPolicy->retention_days ?? $retentionDays);
                $source = $tenantPolicy ? 'tenant' : 'platform';
            }

            return new AuditPolicyEffectiveStateData(
                enabled: true,
                samplingRate: 1.0,
                retentionDays: $retentionDays,
                source: $source,
            );
        }

        $enabled = $definition['defaultEnabled'];
        $samplingRate = $definition['defaultSamplingRate'];
        $retentionDays = $definition['defaultRetentionDays'];
        $source = 'default';

        $platformPolicy = $this->scopePolicies(null)[$action] ?? null;
        if ($platformPolicy instanceof AuditPolicy) {
            $enabled = (bool) $platformPolicy->enabled;
            $samplingRate = $this->normalizeSamplingRate($platformPolicy->sampling_rate);
            $retentionDays = $this->normalizeRetentionDays($platformPolicy->retention_days ?? $retentionDays);
            $source = 'platform';
        }

        if ($tenantId !== null) {
            $tenantPolicy = $this->scopePolicies($tenantId)[$action] ?? null;
            if ($tenantPolicy instanceof AuditPolicy) {
                $enabled = (bool) $tenantPolicy->enabled;
                $samplingRate = $this->normalizeSamplingRate($tenantPolicy->sampling_rate);
                $retentionDays = $this->normalizeRetentionDays($tenantPolicy->retention_days ?? $retentionDays);
                $source = 'tenant';
            }
        }

        return new AuditPolicyEffectiveStateData(
            enabled: $enabled,
            samplingRate: $samplingRate,
            retentionDays: $retentionDays,
            source: $source,
        );
    }

    /**
     * @return array<string, AuditPolicy>
     */
    private function scopePolicies(?int $tenantId): array
    {
        $scopeId = $tenantId ?? AuditPolicy::PLATFORM_SCOPE_ID;

        if (! isset($this->scopePoliciesCache[$scopeId])) {
            $this->scopePoliciesCache[$scopeId] = AuditPolicy::query()
                ->where('tenant_scope_id', $scopeId)
                ->get()
                ->keyBy(static fn (AuditPolicy $policy): string => (string) $policy->action)
                ->all();
        }

        return $this->scopePoliciesCache[$scopeId];
    }

    private function flushCaches(): void
    {
        $this->scopePoliciesCache = [];
    }

    /**
     * @param  Builder<AuditLog>  $query
     */
    private function runPruneQuery(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $query->count();
        }

        return (int) $query->delete();
    }

    private function normalizeSamplingRate(mixed $rate): float
    {
        $value = (float) $rate;
        if ($value < 0) {
            $value = 0.0;
        } elseif ($value > 1) {
            $value = 1.0;
        }

        return round($value, 4);
    }

    private function normalizeRetentionDays(mixed $retentionDays): int
    {
        $days = (int) $retentionDays;

        if ($days < 1) {
            $days = 1;
        } elseif ($days > 3650) {
            $days = 3650;
        }

        return $days;
    }

    private function mandatoryDefaultRetentionDays(): int
    {
        return $this->normalizeRetentionDays(config('audit.retention.mandatory_days', 365));
    }

    private function optionalDefaultRetentionDays(): int
    {
        return $this->normalizeRetentionDays(config('audit.retention.optional_days', 90));
    }

    private function optionalDefaultSamplingRate(): float
    {
        return $this->normalizeSamplingRate(config('audit.sampling.default_optional_rate', 1.0));
    }

    /**
     * @return list<string>
     */
    private function normalizeActionList(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        $normalized = [];
        foreach ($actions as $action) {
            $actionName = trim((string) $action);
            if ($actionName === '') {
                continue;
            }

            $normalized[] = $actionName;
        }

        return $normalized;
    }
}
