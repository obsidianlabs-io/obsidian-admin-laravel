<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Access\Models\UserPreference;
use App\Domains\Auth\Actions\UpdateOwnProfileAction;
use App\Domains\Shared\Events\DomainAuditEvent;
use App\Http\Requests\Api\Auth\UpdatePreferredLocaleRequest;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\UpdateUserPreferencesRequest;
use App\Support\ApiDateTime;
use App\Support\ApiResultCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserProfileController extends AbstractUserController
{
    public function getUserInfo(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $user->loadMissing('role.permissions', 'tenant', 'preference');
        $tenantContext = $this->resolveTenantContext($request, $user);
        if ($tenantContext->failed()) {
            return $this->error($tenantContext->code(), $tenantContext->message());
        }

        return $this->success($this->resolveUserInfo->handle($user, $tenantContext)->toArray());
    }

    public function menus(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $user->loadMissing('role.permissions', 'tenant');
        $tenantContext = $this->resolveTenantContext($request, $user);
        if ($tenantContext->failed()) {
            return $this->error($tenantContext->code(), $tenantContext->message());
        }

        $roles = $this->resolveRoles($user);
        $permissionCodes = $user->permissionCodes();
        $navigation = $this->menuMetadataService->resolveForUser(
            user: $user,
            tenantId: $tenantContext->tenantId(),
            roleCodes: $roles->codes(),
            permissionCodes: $permissionCodes
        );

        return $this->success($navigation->toArray());
    }

    public function me(Request $request): JsonResponse
    {
        return $this->getUserInfo($request);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();

        return $this->success($this->resolveProfile($user)->toArray());
    }

    public function updateProfile(UpdateProfileRequest $request, UpdateOwnProfileAction $updateOwnProfile): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();

        $optimisticLockError = $this->ensureOptimisticLock($request, $user, 'User profile');
        if ($optimisticLockError) {
            return $optimisticLockError;
        }

        $input = $request->toDTO();
        if ($input->password !== null && ! Hash::check((string) $input->currentPassword, $user->password)) {
            return $this->error(ApiResultCode::PARAM_ERROR, 'Current password is incorrect');
        }

        $result = DB::transaction(function () use ($updateOwnProfile, $user, $input) {
            return $updateOwnProfile->handle($user, $input->toUpdateOwnProfileDTO());
        });

        ApiDateTime::assignRequestTimezone($request, $result->timezone());

        DB::afterCommit(static function () use ($user, $result) {
            event(DomainAuditEvent::make(
                action: 'user.profile.update',
                auditable: $user,
                actor: $user,
                oldValues: $result->oldProfile()->toArray(),
                newValues: $result->newProfile()->toArray(),
                tenantId: $user->tenant_id ? (int) $user->tenant_id : null
            ));
        });

        return $this->success($this->resolveProfile($user)->toArray(), 'Profile updated');
    }

    public function updatePreferredLocale(UpdatePreferredLocaleRequest $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();

        $input = $request->toDTO();
        $locale = $input->locale;
        $oldLocale = $this->resolveLocale($user);
        if ($oldLocale === $locale) {
            return $this->success([
                'locale' => $locale,
                'preferredLocale' => $locale,
            ], 'Preferred locale updated');
        }

        DB::transaction(function () use ($user, $locale) {
            $this->upsertUserPreference((int) $user->id, ['locale' => $locale]);
        });

        DB::afterCommit(static function () use ($user, $oldLocale, $locale) {
            event(DomainAuditEvent::make(
                action: 'user.locale.update',
                auditable: $user,
                actor: $user,
                oldValues: ['locale' => $oldLocale],
                newValues: ['locale' => $locale],
                tenantId: $user->tenant_id ? (int) $user->tenant_id : null
            ));
        });

        return $this->success([
            'locale' => $locale,
            'preferredLocale' => $locale,
        ], 'Preferred locale updated');
    }

    public function updateUserPreferences(UpdateUserPreferencesRequest $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        $user = $authResult->requireUser();
        $input = $request->toDTO();
        $oldThemeSchema = $this->resolveThemeSchema($user);
        $oldTimezone = $this->resolveTimezone($user);
        $themeSchema = $input->hasThemeSchema ? $input->themeSchema : $oldThemeSchema;
        $timezone = $input->hasTimezone
            ? ApiDateTime::normalizeTimezone((string) $input->timezone)
            : $oldTimezone;

        if (($oldThemeSchema ?? '') === ($themeSchema ?? '') && $oldTimezone === $timezone) {
            return $this->success([
                'themeSchema' => $themeSchema,
                'timezone' => $timezone,
            ], 'Preferences updated');
        }

        $payload = [];
        if ($input->hasThemeSchema) {
            $payload['theme_schema'] = $themeSchema;
        }
        if ($input->hasTimezone) {
            $payload['timezone'] = $timezone;
        }

        DB::transaction(function () use ($user, $payload) {
            $this->upsertUserPreference((int) $user->id, $payload);
        });

        ApiDateTime::assignRequestTimezone($request, $timezone);

        DB::afterCommit(static function () use ($user, $oldThemeSchema, $oldTimezone, $themeSchema, $timezone) {
            event(DomainAuditEvent::make(
                action: 'user.preferences.update',
                auditable: $user,
                actor: $user,
                oldValues: [
                    'themeSchema' => $oldThemeSchema,
                    'timezone' => $oldTimezone,
                ],
                newValues: [
                    'themeSchema' => $themeSchema,
                    'timezone' => $timezone,
                ],
                tenantId: $user->tenant_id ? (int) $user->tenant_id : null
            ));
        });

        return $this->success([
            'themeSchema' => $themeSchema,
            'timezone' => $timezone,
        ], 'Preferences updated');
    }

    public function timezones(Request $request): JsonResponse
    {
        $authResult = $this->resolveAuthenticatedUser($request);
        if ($authResult->failed()) {
            return $this->error($authResult->code(), $authResult->message());
        }

        return $this->success([
            'defaultTimezone' => ApiDateTime::defaultTimezone(),
            'records' => ApiDateTime::listTimezoneOptions(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertUserPreference(int $userId, array $payload): void
    {
        UserPreference::query()->updateOrCreate(
            ['user_id' => $userId],
            $payload
        );

        $this->resolveUserContext->invalidateUserContextCache();
    }
}
