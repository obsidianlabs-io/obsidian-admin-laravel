<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use Laravel\Sanctum\PersonalAccessToken;

final class SessionTokenAbilityCodec
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

    public function normalizeSessionId(?string $sessionId): ?string
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

    /**
     * @return list<string>
     */
    public function buildSessionClientContextAbilities(SessionClientContextData $context): array
    {
        $abilities = [];

        if ($context->deviceName) {
            $abilities[] = self::CLIENT_DEVICE_NAME_ABILITY_PREFIX.$this->encodeAbilityValue($context->deviceName);
        }
        if ($context->deviceAlias) {
            $abilities[] = self::CLIENT_ALIAS_ABILITY_PREFIX.$this->encodeAbilityValue($context->deviceAlias);
        }
        if ($context->browser) {
            $abilities[] = self::CLIENT_BROWSER_ABILITY_PREFIX.$this->encodeAbilityValue($context->browser);
        }
        if ($context->os) {
            $abilities[] = self::CLIENT_OS_ABILITY_PREFIX.$this->encodeAbilityValue($context->os);
        }
        if ($context->deviceType) {
            $abilities[] = self::CLIENT_DEVICE_TYPE_ABILITY_PREFIX.$this->encodeAbilityValue($context->deviceType);
        }
        if ($context->ipAddress) {
            $abilities[] = self::CLIENT_IP_ABILITY_PREFIX.$this->encodeAbilityValue($context->ipAddress);
        }

        return $abilities;
    }

    /**
     * @return list<string>
     */
    public function resolveAbilities(PersonalAccessToken $token): array
    {
        $abilities = $token->abilities;
        if (! is_array($abilities)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $ability): string => (string) $ability, $abilities)));
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

    public function sessionKeyForToken(PersonalAccessToken $token): string
    {
        $sessionId = $this->resolveSessionId($token);
        if ($sessionId !== null) {
            return $sessionId;
        }

        return 'legacy:'.$token->id;
    }

    public function resolveTokenKind(PersonalAccessToken $token): string
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

    public function resolveSessionClientContext(PersonalAccessToken $token): SessionClientContextData
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

        return SessionClientContextData::fromArray($result);
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public function withUpdatedDeviceAlias(array $abilities, ?string $alias): array
    {
        $normalizedAlias = SessionClientContextData::sanitize($alias, 80);

        return $this->upsertAbilityByPrefix(
            $abilities,
            self::CLIENT_ALIAS_ABILITY_PREFIX,
            $normalizedAlias
        );
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

    private function encodeAbilityValue(string $value): string
    {
        return rawurlencode($value);
    }

    private function decodeAbilityValue(string $value): string
    {
        return rawurldecode($value);
    }

    public function sessionAbility(string $sessionId): string
    {
        return self::SESSION_ABILITY_PREFIX.$sessionId;
    }

    public function accessTokenKindAbility(): string
    {
        return self::TOKEN_KIND_ABILITY_PREFIX.'access';
    }

    public function refreshTokenKindAbility(): string
    {
        return self::TOKEN_KIND_ABILITY_PREFIX.'refresh';
    }
}
