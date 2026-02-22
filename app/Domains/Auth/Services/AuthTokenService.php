<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenService
{
    public const ACCESS_TOKEN_NAME = 'obsidian-access-token';

    public const REFRESH_TOKEN_NAME = 'obsidian-refresh-token';

    private const SESSION_ABILITY_PREFIX = 'session:';

    private const TOKEN_KIND_ABILITY_PREFIX = 'token-kind:';

    private const CLIENT_DEVICE_NAME_ABILITY_PREFIX = 'client-device-name:';

    private const CLIENT_ALIAS_ABILITY_PREFIX = 'client-alias:';

    private const CLIENT_BROWSER_ABILITY_PREFIX = 'client-browser:';

    private const CLIENT_OS_ABILITY_PREFIX = 'client-os:';

    private const CLIENT_DEVICE_TYPE_ABILITY_PREFIX = 'client-device-type:';

    private const CLIENT_IP_ABILITY_PREFIX = 'client-ip:';

    /**
     * @param  array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }  $sessionClientContext
     * @return array{token: string, refreshToken: string}
     */
    public function issueTokenPair(
        User $user,
        bool $rememberMe = false,
        ?string $sessionId = null,
        array $sessionClientContext = []
    ): array {
        $sessionId = $this->normalizeSessionId($sessionId) ?? (string) Str::uuid();

        if ((bool) config('security.auth_tokens.single_device_login', true)) {
            $user->tokens()
                ->whereIn('name', [self::ACCESS_TOKEN_NAME, self::REFRESH_TOKEN_NAME])
                ->delete();
        }

        $accessTokenTtl = $rememberMe
            ? (int) env('API_REMEMBER_ACCESS_TOKEN_TTL_MINUTES', 240)
            : (int) env('API_ACCESS_TOKEN_TTL_MINUTES', 120);
        $refreshTokenTtl = $rememberMe
            ? (int) env('API_REMEMBER_REFRESH_TOKEN_TTL_DAYS', 30)
            : (int) env('API_REFRESH_TOKEN_TTL_DAYS', 7);

        $clientContextAbilities = $this->buildSessionClientContextAbilities($sessionClientContext);

        $accessAbilities = array_merge(
            ['access-api', self::SESSION_ABILITY_PREFIX.$sessionId, self::TOKEN_KIND_ABILITY_PREFIX.'access'],
            $clientContextAbilities
        );
        $refreshAbilities = array_merge(
            ['refresh-token', self::SESSION_ABILITY_PREFIX.$sessionId, self::TOKEN_KIND_ABILITY_PREFIX.'refresh'],
            $clientContextAbilities
        );

        if ($rememberMe) {
            $accessAbilities[] = 'remember-me';
            $refreshAbilities[] = 'remember-me';
        }

        $accessToken = $user->createToken(
            self::ACCESS_TOKEN_NAME,
            $accessAbilities,
            now()->addMinutes(max(1, $accessTokenTtl))
        );

        $refreshToken = $user->createToken(
            self::REFRESH_TOKEN_NAME,
            $refreshAbilities,
            now()->addDays(max(1, $refreshTokenTtl))
        );

        return [
            'token' => $accessToken->plainTextToken,
            'refreshToken' => $refreshToken->plainTextToken,
        ];
    }

    public function resolveSessionId(PersonalAccessToken $token): ?string
    {
        foreach ($this->resolveAbilities($token) as $ability) {
            if (str_starts_with($ability, self::SESSION_ABILITY_PREFIX)) {
                return $this->normalizeSessionId(substr($ability, strlen(self::SESSION_ABILITY_PREFIX)));
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *   sessionId: string,
     *   current: bool,
     *   legacy: bool,
     *   rememberMe: bool,
     *   hasAccessToken: bool,
     *   hasRefreshToken: bool,
     *   tokenCount: int,
     *   createdAt: ?\Illuminate\Support\Carbon,
     *   lastUsedAt: ?\Illuminate\Support\Carbon,
     *   lastAccessUsedAt: ?\Illuminate\Support\Carbon,
     *   lastRefreshUsedAt: ?\Illuminate\Support\Carbon,
     *   accessTokenExpiresAt: ?\Illuminate\Support\Carbon,
     *   refreshTokenExpiresAt: ?\Illuminate\Support\Carbon,
     *   deviceAlias: ?string,
     *   deviceName: ?string,
     *   browser: ?string,
     *   os: ?string,
     *   deviceType: ?string,
     *   ipAddress: ?string
     * }>
     */
    public function listSessions(User $user, ?PersonalAccessToken $currentAccessToken = null): array
    {
        $tokens = $this->managedTokensQuery($user)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get(['id', 'name', 'abilities', 'created_at', 'last_used_at', 'expires_at']);

        $currentSessionKey = null;
        if ($currentAccessToken) {
            $currentSessionKey = $this->sessionKeyForToken($currentAccessToken);
        }

        /** @var array<string, array{
         *   sessionId: string,
         *   current: bool,
         *   legacy: bool,
         *   rememberMe: bool,
         *   hasAccessToken: bool,
         *   hasRefreshToken: bool,
         *   tokenCount: int,
         *   createdAt: ?\Illuminate\Support\Carbon,
         *   lastUsedAt: ?\Illuminate\Support\Carbon,
         *   lastAccessUsedAt: ?\Illuminate\Support\Carbon,
         *   lastRefreshUsedAt: ?\Illuminate\Support\Carbon,
         *   accessTokenExpiresAt: ?\Illuminate\Support\Carbon,
         *   refreshTokenExpiresAt: ?\Illuminate\Support\Carbon,
         *   deviceAlias: ?string,
         *   deviceName: ?string,
         *   browser: ?string,
         *   os: ?string,
         *   deviceType: ?string,
         *   ipAddress: ?string
         * }>
         */
        $groups = [];

        foreach ($tokens as $token) {
            $sessionKey = $this->sessionKeyForToken($token);
            $sessionId = $this->resolveSessionId($token) ?? $sessionKey;
            $isLegacy = str_starts_with($sessionKey, 'legacy:');
            $tokenKind = $this->resolveTokenKind($token);
            $rememberMe = $token->can('remember-me');
            $clientContext = $this->resolveSessionClientContext($token);

            if (! isset($groups[$sessionKey])) {
                $groups[$sessionKey] = [
                    'sessionId' => $sessionId,
                    'current' => $currentSessionKey !== null && hash_equals($currentSessionKey, $sessionKey),
                    'legacy' => $isLegacy,
                    'rememberMe' => $rememberMe,
                    'hasAccessToken' => false,
                    'hasRefreshToken' => false,
                    'tokenCount' => 0,
                    'createdAt' => null,
                    'lastUsedAt' => null,
                    'lastAccessUsedAt' => null,
                    'lastRefreshUsedAt' => null,
                    'accessTokenExpiresAt' => null,
                    'refreshTokenExpiresAt' => null,
                    'deviceAlias' => null,
                    'deviceName' => null,
                    'browser' => null,
                    'os' => null,
                    'deviceType' => null,
                    'ipAddress' => null,
                ];
            }

            $group = &$groups[$sessionKey];
            $group['tokenCount']++;
            $group['rememberMe'] = $group['rememberMe'] || $rememberMe;

            if ($token->created_at && (! $group['createdAt'] || $token->created_at->lt($group['createdAt']))) {
                $group['createdAt'] = $token->created_at;
            }

            if ($token->last_used_at && (! $group['lastUsedAt'] || $token->last_used_at->gt($group['lastUsedAt']))) {
                $group['lastUsedAt'] = $token->last_used_at;
            }

            if ($tokenKind === 'access') {
                $group['hasAccessToken'] = true;
                $group['accessTokenExpiresAt'] = $this->preferLaterExpiry($group['accessTokenExpiresAt'], $token->expires_at);
                if ($token->last_used_at && (! $group['lastAccessUsedAt'] || $token->last_used_at->gt($group['lastAccessUsedAt']))) {
                    $group['lastAccessUsedAt'] = $token->last_used_at;
                }
            } elseif ($tokenKind === 'refresh') {
                $group['hasRefreshToken'] = true;
                $group['refreshTokenExpiresAt'] = $this->preferLaterExpiry($group['refreshTokenExpiresAt'], $token->expires_at);
                if ($token->last_used_at && (! $group['lastRefreshUsedAt'] || $token->last_used_at->gt($group['lastRefreshUsedAt']))) {
                    $group['lastRefreshUsedAt'] = $token->last_used_at;
                }
            }

            $group['deviceName'] = $this->preferClientContextValue($group['deviceName'], $clientContext['deviceName'] ?? null, $tokenKind);
            $group['deviceAlias'] = $this->preferClientContextValue($group['deviceAlias'], $clientContext['deviceAlias'] ?? null, $tokenKind);
            $group['browser'] = $this->preferClientContextValue($group['browser'], $clientContext['browser'] ?? null, $tokenKind);
            $group['os'] = $this->preferClientContextValue($group['os'], $clientContext['os'] ?? null, $tokenKind);
            $group['deviceType'] = $this->preferClientContextValue($group['deviceType'], $clientContext['deviceType'] ?? null, $tokenKind);
            $group['ipAddress'] = $this->preferClientContextValue($group['ipAddress'], $clientContext['ipAddress'] ?? null, $tokenKind);
            unset($group);
        }

        $records = array_values($groups);

        foreach ($records as &$record) {
            if (! is_string($record['deviceName']) || trim($record['deviceName']) === '') {
                $record['deviceName'] = $this->buildDeviceNameFromParts(
                    is_string($record['browser']) ? $record['browser'] : null,
                    is_string($record['os']) ? $record['os'] : null,
                    is_string($record['deviceType']) ? $record['deviceType'] : null
                );
            }
        }
        unset($record);

        usort($records, static function (array $a, array $b): int {
            $aTs = $a['lastUsedAt']?->getTimestamp() ?? $a['createdAt']?->getTimestamp() ?? 0;
            $bTs = $b['lastUsedAt']?->getTimestamp() ?? $b['createdAt']?->getTimestamp() ?? 0;

            return $bTs <=> $aTs;
        });

        return $records;
    }

    /**
     * @return array{updatedTokenCount: int, updatedCurrentSession: bool, deviceAlias: ?string}
     */
    public function updateSessionAlias(
        User $user,
        string $sessionId,
        ?string $alias,
        ?PersonalAccessToken $currentAccessToken = null
    ): array {
        $normalizedSessionId = $this->normalizeSessionId($sessionId);
        $normalizedAlias = $this->sanitizeSessionClientValue($alias, 80);
        $currentSessionKey = $currentAccessToken ? $this->sessionKeyForToken($currentAccessToken) : null;
        $updatedTokenCount = 0;
        $updatedCurrentSession = false;

        $tokens = $this->managedTokensQuery($user)->get();

        foreach ($tokens as $token) {
            $tokenSessionKey = $this->sessionKeyForToken($token);
            $tokenSessionId = $this->resolveSessionId($token) ?? $tokenSessionKey;

            if (
                ($normalizedSessionId !== null && $tokenSessionId === $normalizedSessionId)
                || ($normalizedSessionId === null && $tokenSessionKey === $sessionId)
            ) {
                $abilities = $this->upsertAbilityByPrefix(
                    $this->resolveAbilities($token),
                    self::CLIENT_ALIAS_ABILITY_PREFIX,
                    $normalizedAlias
                );

                $token->abilities = $abilities;
                $token->save();

                $updatedTokenCount++;

                if ($currentSessionKey !== null && hash_equals($currentSessionKey, $tokenSessionKey)) {
                    $updatedCurrentSession = true;
                }
            }
        }

        return [
            'updatedTokenCount' => $updatedTokenCount,
            'updatedCurrentSession' => $updatedCurrentSession,
            'deviceAlias' => $normalizedAlias,
        ];
    }

    /**
     * @return array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }
     */
    public function buildSessionClientContext(?string $userAgent, ?string $ipAddress): array
    {
        $ua = trim((string) ($userAgent ?? ''));
        $ip = trim((string) ($ipAddress ?? ''));

        $deviceType = $this->detectDeviceType($ua);
        $browser = $this->detectBrowser($ua);
        $os = $this->detectOperatingSystem($ua);
        $deviceName = $this->buildDeviceNameFromParts($browser, $os, $deviceType);

        return array_filter([
            'deviceName' => $this->sanitizeSessionClientValue($deviceName, 80),
            'browser' => $this->sanitizeSessionClientValue($browser, 40),
            'os' => $this->sanitizeSessionClientValue($os, 40),
            'deviceType' => $this->sanitizeSessionClientValue($deviceType, 20),
            'ipAddress' => $this->sanitizeSessionClientValue($ip, 64),
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    /**
     * @return array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }
     */
    public function resolveSessionClientContextMetadata(PersonalAccessToken $token): array
    {
        return $this->resolveSessionClientContext($token);
    }

    /**
     * @return array{deletedTokenCount: int, revokedCurrentSession: bool}
     */
    public function revokeSession(User $user, string $sessionId, ?PersonalAccessToken $currentAccessToken = null): array
    {
        $normalizedSessionId = $this->normalizeSessionId($sessionId);
        $currentSessionKey = $currentAccessToken ? $this->sessionKeyForToken($currentAccessToken) : null;
        $deletedTokenCount = 0;
        $revokedCurrentSession = false;

        $tokens = $this->managedTokensQuery($user)->get();

        foreach ($tokens as $token) {
            $tokenSessionKey = $this->sessionKeyForToken($token);
            $tokenSessionId = $this->resolveSessionId($token) ?? $tokenSessionKey;

            if (
                ($normalizedSessionId !== null && $tokenSessionId === $normalizedSessionId)
                || ($normalizedSessionId === null && $tokenSessionKey === $sessionId)
            ) {
                $deletedTokenCount++;
                if ($currentSessionKey !== null && hash_equals($currentSessionKey, $tokenSessionKey)) {
                    $revokedCurrentSession = true;
                }
                $token->delete();
            }
        }

        return [
            'deletedTokenCount' => $deletedTokenCount,
            'revokedCurrentSession' => $revokedCurrentSession,
        ];
    }

    /**
     * @return MorphMany<PersonalAccessToken, User>
     */
    private function managedTokensQuery(User $user): MorphMany
    {
        return $user->tokens()
            ->whereIn('name', [self::ACCESS_TOKEN_NAME, self::REFRESH_TOKEN_NAME]);
    }

    /**
     * @param  array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }  $sessionClientContext
     * @return list<string>
     */
    private function buildSessionClientContextAbilities(array $sessionClientContext): array
    {
        $context = [
            'deviceName' => $this->sanitizeSessionClientValue($sessionClientContext['deviceName'] ?? null, 80),
            'deviceAlias' => $this->sanitizeSessionClientValue($sessionClientContext['deviceAlias'] ?? null, 80),
            'browser' => $this->sanitizeSessionClientValue($sessionClientContext['browser'] ?? null, 40),
            'os' => $this->sanitizeSessionClientValue($sessionClientContext['os'] ?? null, 40),
            'deviceType' => $this->sanitizeSessionClientValue($sessionClientContext['deviceType'] ?? null, 20),
            'ipAddress' => $this->sanitizeSessionClientValue($sessionClientContext['ipAddress'] ?? null, 64),
        ];

        $abilities = [];
        if ($context['deviceName']) {
            $abilities[] = self::CLIENT_DEVICE_NAME_ABILITY_PREFIX.$this->encodeAbilityValue($context['deviceName']);
        }
        if ($context['deviceAlias']) {
            $abilities[] = self::CLIENT_ALIAS_ABILITY_PREFIX.$this->encodeAbilityValue($context['deviceAlias']);
        }
        if ($context['browser']) {
            $abilities[] = self::CLIENT_BROWSER_ABILITY_PREFIX.$this->encodeAbilityValue($context['browser']);
        }
        if ($context['os']) {
            $abilities[] = self::CLIENT_OS_ABILITY_PREFIX.$this->encodeAbilityValue($context['os']);
        }
        if ($context['deviceType']) {
            $abilities[] = self::CLIENT_DEVICE_TYPE_ABILITY_PREFIX.$this->encodeAbilityValue($context['deviceType']);
        }
        if ($context['ipAddress']) {
            $abilities[] = self::CLIENT_IP_ABILITY_PREFIX.$this->encodeAbilityValue($context['ipAddress']);
        }

        return $abilities;
    }

    /**
     * @return list<string>
     */
    private function resolveAbilities(PersonalAccessToken $token): array
    {
        $abilities = $token->abilities;
        if (! is_array($abilities)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $ability): string => (string) $ability, $abilities)));
    }

    /**
     * @return array{
     *   deviceName?: string,
     *   deviceAlias?: string,
     *   browser?: string,
     *   os?: string,
     *   deviceType?: string,
     *   ipAddress?: string
     * }
     */
    private function resolveSessionClientContext(PersonalAccessToken $token): array
    {
        $result = [];

        foreach ($this->resolveAbilities($token) as $ability) {
            if (str_starts_with($ability, self::CLIENT_DEVICE_NAME_ABILITY_PREFIX)) {
                $result['deviceName'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_DEVICE_NAME_ABILITY_PREFIX)));

                continue;
            }

            if (str_starts_with($ability, self::CLIENT_ALIAS_ABILITY_PREFIX)) {
                $result['deviceAlias'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_ALIAS_ABILITY_PREFIX)));

                continue;
            }

            if (str_starts_with($ability, self::CLIENT_BROWSER_ABILITY_PREFIX)) {
                $result['browser'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_BROWSER_ABILITY_PREFIX)));

                continue;
            }

            if (str_starts_with($ability, self::CLIENT_OS_ABILITY_PREFIX)) {
                $result['os'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_OS_ABILITY_PREFIX)));

                continue;
            }

            if (str_starts_with($ability, self::CLIENT_DEVICE_TYPE_ABILITY_PREFIX)) {
                $result['deviceType'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_DEVICE_TYPE_ABILITY_PREFIX)));

                continue;
            }

            if (str_starts_with($ability, self::CLIENT_IP_ABILITY_PREFIX)) {
                $result['ipAddress'] = $this->decodeAbilityValue(substr($ability, strlen(self::CLIENT_IP_ABILITY_PREFIX)));
            }
        }

        return array_filter($result, static fn (mixed $value): bool => trim((string) $value) !== '');
    }

    private function resolveTokenKind(PersonalAccessToken $token): string
    {
        foreach ($this->resolveAbilities($token) as $ability) {
            if (str_starts_with($ability, self::TOKEN_KIND_ABILITY_PREFIX)) {
                return match (substr($ability, strlen(self::TOKEN_KIND_ABILITY_PREFIX))) {
                    'refresh' => 'refresh',
                    default => 'access',
                };
            }
        }

        return $token->name === self::REFRESH_TOKEN_NAME ? 'refresh' : 'access';
    }

    private function sessionKeyForToken(PersonalAccessToken $token): string
    {
        $sessionId = $this->resolveSessionId($token);
        if ($sessionId !== null) {
            return $sessionId;
        }

        return 'legacy:'.$token->id;
    }

    private function normalizeSessionId(?string $sessionId): ?string
    {
        $value = trim((string) ($sessionId ?? ''));
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_-]{8,128}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function encodeAbilityValue(string $value): string
    {
        return rawurlencode($value);
    }

    private function decodeAbilityValue(string $value): string
    {
        return rawurldecode($value);
    }

    private function sanitizeSessionClientValue(mixed $value, int $maxLength): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > $maxLength) {
            $normalized = substr($normalized, 0, $maxLength);
        }

        return trim($normalized) !== '' ? $normalized : null;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    private function upsertAbilityByPrefix(array $abilities, string $prefix, ?string $value): array
    {
        $result = [];

        foreach ($abilities as $ability) {
            if (str_starts_with($ability, $prefix)) {
                continue;
            }

            $result[] = $ability;
        }

        if ($value !== null && $value !== '') {
            $result[] = $prefix.$this->encodeAbilityValue($value);
        }

        return $result;
    }

    private function preferLaterExpiry(?Carbon $current, ?Carbon $incoming): ?Carbon
    {
        if (! $current instanceof Carbon) {
            return $incoming;
        }

        if (! $incoming instanceof Carbon) {
            return $current;
        }

        return $incoming->gt($current) ? $incoming : $current;
    }

    private function preferClientContextValue(?string $current, ?string $incoming, string $tokenKind): ?string
    {
        if ($incoming === null || trim($incoming) === '') {
            return $current;
        }

        if ($current === null || trim($current) === '') {
            return $incoming;
        }

        if ($tokenKind === 'access') {
            return $incoming;
        }

        return $current;
    }

    private function buildDeviceNameFromParts(?string $browser, ?string $os, ?string $deviceType): ?string
    {
        $browser = $this->sanitizeSessionClientValue($browser, 40);
        $os = $this->sanitizeSessionClientValue($os, 40);
        $deviceType = $this->sanitizeSessionClientValue($deviceType, 20);

        if ($browser && $os) {
            return $browser.' on '.$os;
        }

        if ($browser) {
            return $browser;
        }

        if ($os && $deviceType) {
            return ucfirst($deviceType).' ('.$os.')';
        }

        if ($os) {
            return $os;
        }

        if ($deviceType) {
            return ucfirst($deviceType);
        }

        return null;
    }

    private function detectDeviceType(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (str_contains($ua, 'bot') || str_contains($ua, 'crawler') || str_contains($ua, 'spider')) {
            return 'bot';
        }

        return 'desktop';
    }

    private function detectBrowser(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'edg/')) {
            return 'Edge';
        }

        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }

        if (str_contains($ua, 'chrome/')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'firefox/')) {
            return 'Firefox';
        }

        if (str_contains($ua, 'safari/')) {
            return 'Safari';
        }

        if (str_contains($ua, 'msie') || str_contains($ua, 'trident/')) {
            return 'Internet Explorer';
        }

        return null;
    }

    private function detectOperatingSystem(string $userAgent): ?string
    {
        if ($userAgent === '') {
            return null;
        }

        $ua = strtolower($userAgent);

        return match (true) {
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ios') => 'iOS',
            str_contains($ua, 'mac os x'), str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'linux') => 'Linux',
            default => null,
        };
    }
}
