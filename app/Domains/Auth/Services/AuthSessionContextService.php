<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Auth\Services\Results\SessionRecord;
use App\Domains\Auth\Services\Results\SessionRecordsResult;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;

final class AuthSessionContextService
{
    public function requireAuthenticatedSession(ApiAuthResult $authResult): ApiAuthResult
    {
        if ($authResult->failed()) {
            return $authResult;
        }

        if (! $authResult->token() || ! $authResult->user() instanceof User) {
            return ApiAuthResult::failure('4010', 'Unauthorized');
        }

        return $authResult;
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
     *   createdAt: string,
     *   lastUsedAt: string,
     *   lastAccessUsedAt: string,
     *   lastRefreshUsedAt: string,
     *   accessTokenExpiresAt: string,
     *   refreshTokenExpiresAt: string,
     *   deviceAlias: string,
     *   deviceName: string,
     *   browser: string,
     *   os: string,
     *   deviceType: string,
     *   ipAddress: string
     * }>
     */
    public function mapSessionRecordsForResponse(SessionRecordsResult $sessionRecords, Request $request): array
    {
        $records = $sessionRecords->records();

        return array_map(static function (SessionRecord $record) use ($request): array {
            return [
                'sessionId' => $record->sessionId,
                'current' => $record->current,
                'legacy' => $record->legacy,
                'rememberMe' => $record->rememberMe,
                'hasAccessToken' => $record->hasAccessToken,
                'hasRefreshToken' => $record->hasRefreshToken,
                'tokenCount' => $record->tokenCount,
                'createdAt' => ApiDateTime::formatForRequest($record->createdAt, $request),
                'lastUsedAt' => ApiDateTime::formatForRequest($record->lastUsedAt, $request),
                'lastAccessUsedAt' => ApiDateTime::formatForRequest($record->lastAccessUsedAt, $request),
                'lastRefreshUsedAt' => ApiDateTime::formatForRequest($record->lastRefreshUsedAt, $request),
                'accessTokenExpiresAt' => ApiDateTime::formatForRequest($record->accessTokenExpiresAt, $request),
                'refreshTokenExpiresAt' => ApiDateTime::formatForRequest($record->refreshTokenExpiresAt, $request),
                'deviceAlias' => (string) ($record->deviceAlias ?? ''),
                'deviceName' => (string) ($record->deviceName ?? ''),
                'browser' => (string) ($record->browser ?? ''),
                'os' => (string) ($record->os ?? ''),
                'deviceType' => (string) ($record->deviceType ?? ''),
                'ipAddress' => (string) ($record->ipAddress ?? ''),
            ];
        }, $records);
    }
}
