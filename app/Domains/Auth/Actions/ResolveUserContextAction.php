<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Access\Models\Role;
use App\Domains\Access\Models\User;
use App\Domains\Access\Models\UserPreference;
use App\Domains\Shared\Services\ApiCacheService;
use App\Domains\System\Models\Language;
use App\Support\ApiDateTime;
use App\Support\LocaleDefaults;

final class ResolveUserContextAction
{
    public function __construct(private readonly ApiCacheService $apiCacheService) {}

    /**
     * @var list<string>|null
     */
    private ?array $activeLocaleCodes = null;

    /**
     * @return list<string>
     */
    public function resolveRoles(User $user): array
    {
        $user->loadMissing('role');

        $role = $user->role;
        $roleCode = $role instanceof Role ? (string) $role->code : 'R_USER';

        return [$roleCode];
    }

    /**
     * @return array{
     *   userId: string,
     *   userName: string,
     *   locale: string,
     *   preferredLocale: string,
     *   timezone: string,
     *   themeSchema: string|null,
     *   email: string,
     *   roleCode: string,
     *   roleName: string,
     *   tenantId: string,
     *   tenantName: string,
     *   twoFactorEnabled: bool,
     *   status: string,
     *   version: string,
     *   createTime: string,
     *   updateTime: string
     * }
     */
    public function resolveProfile(User $user): array
    {
        $user->loadMissing('role:id,code,name', 'tenant:id,name', 'preference');
        $preference = $user->preference;
        $preferenceUpdatedAt = $preference instanceof UserPreference
            ? (int) ($preference->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0)
            : 0;
        $signature = sprintf(
            'user:%d|updated_at:%d|preference_updated_at:%d|v_users:%d|v_roles:%d|v_tenants:%d|v_languages:%d',
            (int) $user->id,
            (int) ($user->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            $preferenceUpdatedAt,
            $this->apiCacheService->version('users'),
            $this->apiCacheService->version('roles'),
            $this->apiCacheService->version('tenants'),
            $this->apiCacheService->version('languages'),
        );

        /** @var array{
         *   userId: string,
         *   userName: string,
         *   locale: string,
         *   preferredLocale: string,
         *   timezone: string,
         *   themeSchema: string|null,
         *   email: string,
         *   roleCode: string,
         *   roleName: string,
         *   tenantId: string,
         *   tenantName: string,
         *   twoFactorEnabled: bool,
         *   status: string,
         *   version: string,
         *   createTime: string,
         *   updateTime: string
         * } $resolved
         */
        $resolved = $this->apiCacheService->remember(
            namespace: 'auth.profile',
            signature: $signature,
            resolver: fn (): array => $this->buildProfilePayload($user),
            ttlSeconds: max(1, (int) config('api.user_profile_cache_ttl_seconds', 180))
        );

        return $resolved;
    }

    public function syncLocaleOnLogin(User $user, string $locale): void
    {
        $nextLocale = trim($locale);
        if ($nextLocale === '') {
            return;
        }

        $currentLocale = $this->resolveLocale($user);
        if ($currentLocale === $nextLocale) {
            return;
        }

        UserPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['locale' => $nextLocale]
        );
        $user->unsetRelation('preference');
        $this->invalidateUserContextCache();
    }

    public function resolveLocale(User $user): string
    {
        $user->loadMissing('preference');

        $preference = $user->preference;
        $locale = $preference instanceof UserPreference ? (string) $preference->locale : '';

        return $this->isActiveLocaleCode($locale) ? $locale : $this->resolveDefaultLocaleCode();
    }

    public function resolveThemeSchema(User $user): ?string
    {
        $user->loadMissing('preference');

        $preference = $user->preference;
        $themeSchema = $preference instanceof UserPreference ? (string) $preference->theme_schema : '';

        return in_array($themeSchema, ['light', 'dark', 'auto'], true) ? $themeSchema : null;
    }

    public function resolveTimezone(User $user): string
    {
        return ApiDateTime::resolveUserTimezone($user);
    }

    public function invalidateUserContextCache(): void
    {
        $this->apiCacheService->bump('users');
    }

    private function resolveDefaultLocaleCode(): string
    {
        return LocaleDefaults::resolve();
    }

    private function isActiveLocaleCode(string $locale): bool
    {
        $normalizedLocale = trim($locale);
        if ($normalizedLocale === '') {
            return false;
        }

        if ($this->activeLocaleCodes === null) {
            $codes = Language::query()
                ->where('status', '1')
                ->orderByDesc('is_default')
                ->orderBy('sort')
                ->orderBy('id')
                ->pluck('code')
                ->all();

            $resolvedCodes = [];
            foreach ($codes as $code) {
                $value = trim((string) $code);
                if ($value !== '') {
                    $resolvedCodes[] = $value;
                }
            }

            $this->activeLocaleCodes = $resolvedCodes;
        }

        return in_array($normalizedLocale, $this->activeLocaleCodes, true);
    }

    /**
     * @return array{
     *   userId: string,
     *   userName: string,
     *   locale: string,
     *   preferredLocale: string,
     *   timezone: string,
     *   themeSchema: string|null,
     *   email: string,
     *   roleCode: string,
     *   roleName: string,
     *   tenantId: string,
     *   tenantName: string,
     *   twoFactorEnabled: bool,
     *   status: string,
     *   version: string,
     *   createTime: string,
     *   updateTime: string
     * }
     */
    private function buildProfilePayload(User $user): array
    {
        $locale = $this->resolveLocale($user);
        $timezone = $this->resolveTimezone($user);
        $themeSchema = $this->resolveThemeSchema($user);
        $role = $user->role;
        $tenant = $user->tenant;
        $tenantName = is_object($tenant) && isset($tenant->name) ? (string) $tenant->name : 'No Tenant';

        return [
            'userId' => (string) $user->id,
            'userName' => (string) $user->name,
            'locale' => $locale,
            'preferredLocale' => $locale,
            'timezone' => $timezone,
            'themeSchema' => $themeSchema,
            'email' => (string) $user->email,
            'roleCode' => $role instanceof Role ? (string) $role->code : '',
            'roleName' => $role instanceof Role ? (string) $role->name : 'No Role',
            'tenantId' => $user->tenant_id ? (string) $user->tenant_id : '',
            'tenantName' => $tenantName,
            'twoFactorEnabled' => (bool) $user->two_factor_enabled,
            'status' => in_array((string) $user->status, ['1', '2'], true) ? (string) $user->status : '1',
            'version' => (string) ($user->updated_at?->copy()->setTimezone('UTC')->timestamp ?? 0),
            'createTime' => ApiDateTime::format($user->created_at, $timezone),
            'updateTime' => ApiDateTime::format($user->updated_at, $timezone),
        ];
    }
}
