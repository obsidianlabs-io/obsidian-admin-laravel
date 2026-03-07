<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Services\Results\SessionRecord;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

final class SessionRecordBuilder
{
    private bool $rememberMe = false;

    private bool $hasAccessToken = false;

    private bool $hasRefreshToken = false;

    private int $tokenCount = 0;

    private ?Carbon $createdAt = null;

    private ?Carbon $lastUsedAt = null;

    private ?Carbon $lastAccessUsedAt = null;

    private ?Carbon $lastRefreshUsedAt = null;

    private ?Carbon $accessTokenExpiresAt = null;

    private ?Carbon $refreshTokenExpiresAt = null;

    private ?string $deviceAlias = null;

    private ?string $deviceName = null;

    private ?string $browser = null;

    private ?string $os = null;

    private ?string $deviceType = null;

    private ?string $ipAddress = null;

    public function __construct(
        private readonly string $sessionId,
        private readonly bool $current,
        private readonly bool $legacy,
    ) {}

    public function applyToken(
        PersonalAccessToken $token,
        string $tokenKind,
        bool $rememberMe,
        SessionClientContextData $clientContext,
    ): void {
        $this->tokenCount++;
        $this->rememberMe = $this->rememberMe || $rememberMe;

        if ($token->created_at && (! $this->createdAt || $token->created_at->lt($this->createdAt))) {
            $this->createdAt = $token->created_at;
        }

        if ($token->last_used_at && (! $this->lastUsedAt || $token->last_used_at->gt($this->lastUsedAt))) {
            $this->lastUsedAt = $token->last_used_at;
        }

        if ($tokenKind === 'access') {
            $this->hasAccessToken = true;
            $this->accessTokenExpiresAt = $this->preferLaterExpiry($this->accessTokenExpiresAt, $token->expires_at);
            if ($token->last_used_at && (! $this->lastAccessUsedAt || $token->last_used_at->gt($this->lastAccessUsedAt))) {
                $this->lastAccessUsedAt = $token->last_used_at;
            }
        } elseif ($tokenKind === 'refresh') {
            $this->hasRefreshToken = true;
            $this->refreshTokenExpiresAt = $this->preferLaterExpiry($this->refreshTokenExpiresAt, $token->expires_at);
            if ($token->last_used_at && (! $this->lastRefreshUsedAt || $token->last_used_at->gt($this->lastRefreshUsedAt))) {
                $this->lastRefreshUsedAt = $token->last_used_at;
            }
        }

        $this->deviceName = $this->preferClientContextValue($this->deviceName, $clientContext->deviceName, $tokenKind);
        $this->deviceAlias = $this->preferClientContextValue($this->deviceAlias, $clientContext->deviceAlias, $tokenKind);
        $this->browser = $this->preferClientContextValue($this->browser, $clientContext->browser, $tokenKind);
        $this->os = $this->preferClientContextValue($this->os, $clientContext->os, $tokenKind);
        $this->deviceType = $this->preferClientContextValue($this->deviceType, $clientContext->deviceType, $tokenKind);
        $this->ipAddress = $this->preferClientContextValue($this->ipAddress, $clientContext->ipAddress, $tokenKind);
    }

    public function toRecord(UserAgentParser $userAgentParser): SessionRecord
    {
        $deviceName = $this->deviceName;
        if ($deviceName === null || trim($deviceName) === '') {
            $deviceName = $userAgentParser->buildDeviceNameFromParts(
                $this->browser,
                $this->os,
                $this->deviceType,
            );
        }

        return new SessionRecord(
            sessionId: $this->sessionId,
            current: $this->current,
            legacy: $this->legacy,
            rememberMe: $this->rememberMe,
            hasAccessToken: $this->hasAccessToken,
            hasRefreshToken: $this->hasRefreshToken,
            tokenCount: $this->tokenCount,
            createdAt: $this->createdAt,
            lastUsedAt: $this->lastUsedAt,
            lastAccessUsedAt: $this->lastAccessUsedAt,
            lastRefreshUsedAt: $this->lastRefreshUsedAt,
            accessTokenExpiresAt: $this->accessTokenExpiresAt,
            refreshTokenExpiresAt: $this->refreshTokenExpiresAt,
            deviceAlias: $this->deviceAlias,
            deviceName: $deviceName,
            browser: $this->browser,
            os: $this->os,
            deviceType: $this->deviceType,
            ipAddress: $this->ipAddress,
        );
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
}
