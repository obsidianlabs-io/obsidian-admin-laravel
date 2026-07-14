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
    public function __construct(
        private readonly AuditPolicyResolver $resolver,
    ) {}

    /**
     * @return list<AuditPolicyRecordData>
     */
    public function listEffectivePolicies(?int $tenantId): array
    {
        $records = [];
        $catalog = $this->resolver->eventCatalog();

        foreach ($catalog as $action => $definition) {
            $effective = $this->resolver->resolveEffectivePolicy($action, $tenantId);
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

        return $records;
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
        $catalog = $this->resolver->eventCatalog();
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
            $oldEffective = $this->resolver->resolveEffectivePolicy($action, $tenantId);
            $oldValues = $oldEffective->toRuleArray();
            $enabled = $record->enabled;
            $samplingRate = $this->resolver->normalizeSamplingRate($record->samplingRate ?? $definition['defaultSamplingRate']);
            $retentionDays = $this->resolver->normalizeRetentionDays($record->retentionDays ?? $definition['defaultRetentionDays']);

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
                && $this->resolver->normalizeSamplingRate($existing->sampling_rate) === $samplingRate
                && $this->resolver->normalizeRetentionDays($existing->retention_days ?? $retentionDays) === $retentionDays
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
                samplingRate: $this->resolver->normalizeSamplingRate($updated->sampling_rate),
                retentionDays: $this->resolver->normalizeRetentionDays((int) ($updated->retention_days ?? $retentionDays)),
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

        $this->resolver->flushCaches();

        return new AuditPolicyUpdateResultData(
            updated: count($changes),
            changes: $changes,
        );
    }

    /**
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

            $this->resolver->flushCaches();
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
                    'policy_snapshot' => array_map(static fn (AuditPolicyRecordData $r): array => $r->toArray(), $snapshot),
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
        $effective = $this->resolver->resolveEffectivePolicy($action, $tenantId);

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
        $catalog = $this->resolver->eventCatalog();
        $knownActions = array_keys($catalog);
        $now = now();
        $totalDeleted = 0;

        foreach ($catalog as $action => $definition) {
            $retentionDays = $this->resolver->resolveEffectivePolicy($action, null)->retentionDays;
            $cutoff = $now->copy()->subDays($retentionDays);

            $deleted = $this->runPruneQuery(
                AuditLog::query()
                    ->forAction($action)
                    ->beforeTimestamp($cutoff),
                $dryRun
            );
            $totalDeleted += $deleted;
        }

        $unknownCutoff = $now->copy()->subDays($this->resolver->optionalDefaultRetentionDays());
        $unknownQuery = AuditLog::query()->beforeTimestamp($unknownCutoff);
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
     * @param  Builder<AuditLog>  $query
     */
    private function runPruneQuery(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $query->count();
        }

        return (int) $query->delete();
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
