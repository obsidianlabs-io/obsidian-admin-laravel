<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Access\Models\User;
use App\Domains\Shared\Auth\ApiAuthResult;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthSessionContextService
{
    /**
     * @return array{ok: false, code: string, msg: string}|array{
     *   ok: true,
     *   user: User,
     *   token: PersonalAccessToken
     * }
     */
    public function requireAuthenticatedSession(ApiAuthResult $authResult): array
    {
        if ($authResult->failed()) {
            return [
                'ok' => false,
                'code' => $authResult->code(),
                'msg' => $authResult->message(),
            ];
        }

        $user = $authResult->user();
        $token = $authResult->token();

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            return [
                'ok' => false,
                'code' => '4010',
                'msg' => 'Unauthorized',
            ];
        }

        return [
            'ok' => true,
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * @param  list<array{
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
     * }>  $records
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
    public function mapSessionRecordsForResponse(array $records, Request $request): array
    {
        return array_map(static function (array $record) use ($request): array {
            return [
                'sessionId' => (string) $record['sessionId'],
                'current' => (bool) $record['current'],
                'legacy' => (bool) $record['legacy'],
                'rememberMe' => (bool) $record['rememberMe'],
                'hasAccessToken' => (bool) $record['hasAccessToken'],
                'hasRefreshToken' => (bool) $record['hasRefreshToken'],
                'tokenCount' => (int) $record['tokenCount'],
                'createdAt' => ApiDateTime::formatForRequest($record['createdAt'] ?? null, $request),
                'lastUsedAt' => ApiDateTime::formatForRequest($record['lastUsedAt'] ?? null, $request),
                'lastAccessUsedAt' => ApiDateTime::formatForRequest($record['lastAccessUsedAt'] ?? null, $request),
                'lastRefreshUsedAt' => ApiDateTime::formatForRequest($record['lastRefreshUsedAt'] ?? null, $request),
                'accessTokenExpiresAt' => ApiDateTime::formatForRequest($record['accessTokenExpiresAt'] ?? null, $request),
                'refreshTokenExpiresAt' => ApiDateTime::formatForRequest($record['refreshTokenExpiresAt'] ?? null, $request),
                'deviceAlias' => (string) ($record['deviceAlias'] ?? ''),
                'deviceName' => (string) ($record['deviceName'] ?? ''),
                'browser' => (string) ($record['browser'] ?? ''),
                'os' => (string) ($record['os'] ?? ''),
                'deviceType' => (string) ($record['deviceType'] ?? ''),
                'ipAddress' => (string) ($record['ipAddress'] ?? ''),
            ];
        }, $records);
    }
}
