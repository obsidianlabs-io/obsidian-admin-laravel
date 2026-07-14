<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\System\Data\AuditPolicyEffectiveStateData;
use App\Domains\System\Models\AuditPolicy;

/**
 * Resolves audit policy effective state and manages the event catalog.
 *
 * Extracted from AuditPolicyService to reduce its complexity.
 */
class AuditPolicyResolver
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
     * }>|null
     */
    private ?array $eventCatalogCache = null;

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
    public function eventCatalog(): array
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
    public function eventDefinition(string $action): ?array
    {
        return $this->eventCatalog()[$action] ?? null;
    }

    public function resolveEffectivePolicy(string $action, ?int $tenantId): AuditPolicyEffectiveStateData
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
    public function scopePolicies(?int $tenantId): array
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

    public function flushCaches(): void
    {
        $this->scopePoliciesCache = [];
        $this->eventCatalogCache = null;
    }

    public function normalizeSamplingRate(mixed $rate): float
    {
        $value = (float) $rate;
        if ($value < 0) {
            $value = 0.0;
        } elseif ($value > 1) {
            $value = 1.0;
        }

        return round($value, 4);
    }

    public function normalizeRetentionDays(mixed $retentionDays): int
    {
        $days = (int) $retentionDays;

        if ($days < 1) {
            $days = 1;
        } elseif ($days > 3650) {
            $days = 3650;
        }

        return $days;
    }

    public function mandatoryDefaultRetentionDays(): int
    {
        return $this->normalizeRetentionDays(config('audit.retention.mandatory_days', 365));
    }

    public function optionalDefaultRetentionDays(): int
    {
        return $this->normalizeRetentionDays(config('audit.retention.optional_days', 90));
    }

    public function optionalDefaultSamplingRate(): float
    {
        return $this->normalizeSamplingRate(config('audit.sampling.default_optional_rate', 1.0));
    }
}
