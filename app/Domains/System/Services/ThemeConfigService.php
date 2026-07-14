<?php

declare(strict_types=1);

namespace App\Domains\System\Services;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Data\AuditContext;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Domains\System\Data\EffectiveThemeConfigData;
use App\Domains\System\Data\ThemeActorScopeData;
use App\Domains\System\Data\ThemeScopeConfigData;
use App\Domains\System\Models\ThemeProfile;
use App\DTOs\Theme\UpdateThemeConfigInputDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type ThemeScope 'platform'|'tenant'
 */
class ThemeConfigService
{
    public function __construct(
        private readonly ThemeConfigNormalizer $normalizer,
    ) {}

    public function resolveActorScope(User $user, ?int $tenantId): ThemeActorScopeData
    {
        unset($user, $tenantId);

        return new ThemeActorScopeData(
            scopeType: ThemeProfile::SCOPE_PLATFORM,
            scopeId: null,
            scopeName: 'Project Default',
        );
    }

    public function resolveEffectiveConfig(?int $tenantId, ?string $userThemeSchema = null): EffectiveThemeConfigData
    {
        $defaults = $this->normalizer->defaultConfig();
        $platformScope = $this->scopePayload(ThemeProfile::SCOPE_PLATFORM, null);
        unset($tenantId);

        $merged = array_replace($defaults, $platformScope['config']);
        $sanitized = $this->normalizer->sanitizeConfig($merged);

        $themeSchema = trim((string) ($userThemeSchema ?? ''));
        if (in_array($themeSchema, $this->normalizer->allowedSchemes(), true)) {
            $sanitized['themeScheme'] = $this->normalizer->normalizeThemeScheme($themeSchema, $sanitized['themeScheme']);
        }

        return new EffectiveThemeConfigData(
            config: $sanitized,
            profileVersion: (int) $platformScope['version'],
        );
    }

    public function describeScopeConfig(string $scopeType, ?int $scopeId, string $scopeName): ThemeScopeConfigData
    {
        $scopeType = $this->normalizeScopeType($scopeType);
        $payload = $this->scopePayload($scopeType, $scopeId);

        return new ThemeScopeConfigData(
            scopeType: $scopeType,
            scopeId: $scopeId,
            scopeName: $scopeName,
            config: $this->normalizer->sanitizeConfig($payload['config']),
            version: (int) $payload['version'],
        );
    }

    public function updateScopeConfig(
        string $scopeType,
        ?int $scopeId,
        string $scopeName,
        UpdateThemeConfigInputDTO $changes,
        ?AuditContext $audit = null
    ): ThemeScopeConfigData {
        $before = $this->describeScopeConfig($scopeType, $scopeId, $scopeName);
        $actorUserId = $audit ? (int) $audit->actor->id : 0;
        $scopeType = $this->normalizeScopeType($scopeType);
        $scopeKey = ThemeProfile::scopeKey($scopeType, $scopeId);
        $normalizedChanges = $this->normalizer->extractEditableConfig($changes->toArray());

        $profile = DB::transaction(function () use ($scopeType, $scopeId, $scopeKey, $normalizedChanges, $actorUserId, $scopeName): ThemeProfile {
            $existing = ThemeProfile::query()
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            $currentConfig = $existing instanceof ThemeProfile
                ? $this->normalizeStoredConfig($existing->getAttribute('config'))
                : [];
            $nextConfig = $this->normalizer->sanitizeConfig(array_replace($currentConfig, $normalizedChanges));
            $storedConfig = $this->normalizer->diffFromDefault($nextConfig);

            if (! $existing) {
                return ThemeProfile::query()->create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'scope_key' => $scopeKey,
                    'name' => $scopeName,
                    'status' => '1',
                    'config' => $storedConfig,
                    'version' => 1,
                    'updated_by' => $actorUserId,
                ]);
            }

            if (
                $this->normalizer->sanitizeConfig($currentConfig) === $nextConfig
                && (string) ($existing->name ?? '') === $scopeName
            ) {
                return $existing;
            }

            $existing->fill([
                'name' => $scopeName,
                'status' => '1',
                'config' => $storedConfig,
                'version' => ((int) $existing->version) + 1,
                'updated_by' => $actorUserId,
            ]);
            $existing->save();

            return $existing;
        });

        $this->forgetScopeCache($scopeType, $scopeId);

        $updatedData = new ThemeScopeConfigData(
            scopeType: $scopeType,
            scopeId: $scopeId,
            scopeName: $scopeName,
            config: $this->normalizer->sanitizeConfig($this->normalizeStoredConfig($profile->getAttribute('config'))),
            version: (int) $profile->version,
        );

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $before, $updatedData) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'theme.config.update',
                    auditable: 'theme-profile',
                    actor: $audit->actor,
                    oldValues: $before->toAuditArray(),
                    newValues: $updatedData->toAuditArray(),
                    tenantId: $updatedData->scopeType === 'tenant' ? $updatedData->scopeId : null,
                ));
            });
        }

        return $updatedData;
    }

    public function resetScopeConfig(
        string $scopeType,
        ?int $scopeId,
        string $scopeName,
        ?AuditContext $audit = null
    ): ThemeScopeConfigData {
        $before = $this->describeScopeConfig($scopeType, $scopeId, $scopeName);
        $actorUserId = $audit ? (int) $audit->actor->id : 0;
        $scopeType = $this->normalizeScopeType($scopeType);
        $scopeKey = ThemeProfile::scopeKey($scopeType, $scopeId);

        $profile = DB::transaction(function () use ($scopeType, $scopeId, $scopeName, $scopeKey, $actorUserId): ThemeProfile {
            $existing = ThemeProfile::query()
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                return ThemeProfile::query()->create([
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'scope_key' => $scopeKey,
                    'name' => $scopeName,
                    'status' => '1',
                    'config' => [],
                    'version' => 1,
                    'updated_by' => $actorUserId,
                ]);
            }

            $existing->fill([
                'name' => $scopeName,
                'status' => '1',
                'config' => [],
                'version' => ((int) $existing->version) + 1,
                'updated_by' => $actorUserId,
            ]);
            $existing->save();

            return $existing;
        });

        $this->forgetScopeCache($scopeType, $scopeId);

        $updatedData = new ThemeScopeConfigData(
            scopeType: $scopeType,
            scopeId: $scopeId,
            scopeName: $scopeName,
            config: $this->normalizer->sanitizeConfig([]),
            version: (int) $profile->version,
        );

        if ($audit !== null) {
            DB::afterCommit(static function () use ($audit, $before, $updatedData) {
                event(DomainAuditEvent::make(
                    action: $audit->overrideAction ?? 'theme.config.reset',
                    auditable: 'theme-profile',
                    actor: $audit->actor,
                    oldValues: $before->toAuditArray(),
                    newValues: $updatedData->toAuditArray(),
                    tenantId: $updatedData->scopeType === 'tenant' ? $updatedData->scopeId : null,
                ));
            });
        }

        return $updatedData;
    }

    /**
     * @return array{config: array<string, mixed>, version: int}
     */
    private function scopePayload(string $scopeType, ?int $scopeId): array
    {
        $cacheKey = $this->scopeCacheKey($scopeType, $scopeId);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($scopeType, $scopeId): array {
            $profile = ThemeProfile::query()
                ->where('scope_key', ThemeProfile::scopeKey($scopeType, $scopeId))
                ->where('status', '1')
                ->first();

            $config = $profile instanceof ThemeProfile
                ? $this->normalizeStoredConfig($profile->getAttribute('config'))
                : [];
            $version = $profile instanceof ThemeProfile ? (int) $profile->version : 0;

            return [
                'config' => $config,
                'version' => $version,
            ];
        });
    }

    private function forgetScopeCache(string $scopeType, ?int $scopeId): void
    {
        Cache::forget($this->scopeCacheKey($scopeType, $scopeId));
    }

    private function scopeCacheKey(string $scopeType, ?int $scopeId): string
    {
        $normalizedScopeId = $scopeId ?? 0;

        return sprintf('theme.profile.%s.%d', $scopeType, $normalizedScopeId);
    }

    /**
     * @return ThemeScope
     */
    private function normalizeScopeType(string $scopeType): string
    {
        return $scopeType === ThemeProfile::SCOPE_TENANT
            ? ThemeProfile::SCOPE_TENANT
            : ThemeProfile::SCOPE_PLATFORM;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStoredConfig(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
